<?php
class Tx_DrBlob_Controller_FileController extends Tx_Extbase_MVC_Controller_ActionController {
	
	/**
	 * @var Tx_DrBlob_Domain_Repository_FileRepository
	 */
	protected $fileRepository = null;
	
	/**
	 * Lenght of the filecontent to stream into the output buffer
	 */
	const BUFFER_LEN = 8192;
	
	/**
	 * Initialize Action, automaticlly called by the dispatcher
	 */
	public function initializeAction() {
		$this->fileRepository = t3lib_div::makeInstance( 'Tx_DrBlob_Domain_Repository_FileRepository' );
	}
	
	/**
	 * List-View Mode
	 * 
	 * @param string $sort Field to use for sorting
	 */
	public function indexAction( $sort = null) {
		$this->fileRepository->qryParams['orderBy'] = $sort;
		switch( $this->settings['code'] ) {
			case 'top':  		$filelist = $this->fileRepository->findVipRecords(); 		break;
			case 'personal':  	$filelist = $this->fileRepository->findSubscribedRecords(); break;
			case 'list':
			default: 			$filelist = $this->fileRepository->findAll(); 				break;
		}
		
		$this->view->assign( 'files', $filelist );
	}

	/**
	 * Method to show the details of a given file
	 *
	 * @param Tx_DrBlob_Domain_Model_File $file
	 */
	public function detailsAction( Tx_DrBlob_Domain_Model_File $file ) {
		$this->view->assign( 'file', $file );
	}
	
	/**
	 * The method is called when dr_blob sees an incoming download request.
	 * Therefore it uses manipulates the http-header sent.
	 * The exact type- and amount of header sent out depends on the record type
	 * This method is also used to increment the download counter
	 *
	 * @param Tx_DrBlob_Domain_Model_File $file
	 * 
	 * @internal
	 * 		type=1		The file is decoded and this method controls the download procedure. Therefore the file's content
	 * 					is written to the PHP output buffer in one big piece
	 * 		type=2		The file is either decodes and handled like a type=1-record (which was the old behaviour), or
	 * 					it is sliced into pieces and streamed to the client 
	 * 		type=3		The file is downloaded from a unsecure directory underneath the TYPO3 document root directory
	 * 					Unlike for the other types this download is handled by the webserver, not inside this method.
	 * @internal API spots:	
	 * 		The hook preProcessDownloadHook
	 * 		The TS API call downloadFilenameUserFunc
	 * 
	 * @internal IE6 SSL Bug: http://support.microsoft.com/default.aspx?scid=kb;EN-US;q297822
	 * 
	 * @see		RfC 2045, RfC 2046, RfC 2077 for Content Disposition
	 */
	public function downloadAction( Tx_DrBlob_Domain_Model_File $file ) {
		$this->response->setStatus( 100 );
		
		if( !$file->hasWorkload() ) { $this->response->setStatus( 400 ); }
		if( $insufficent_rights = false ) { $this->response->setStatus( 401 ); }
		if( $file_exists = false ) { $this->response->setStatus( 404 ); }
		if( $deleted_in_T3 = false ) { $this->response->setStatus( 410 ); }
		if( !$this->validateResponseMimeType( $file->getFileMimeType() ) ) { $this->response->setStatus( 415 ); }
		
		if( $this->response->getStatus() == '100 Continue' ) {

			$this->response->setStatus( 200 );
			
				//Post-Load the record workload
			$record = $this->fileRepository->getFileWorkload( $file->getUid() );
			
			switch( $file->getRecordType() ) {
				case '2': 
					$fileReference = Tx_DrBlob_Div::getStorageFolder() . $record['blob_data'];
					
						//asume the file to be quoted --> no streaming possible
					if( empty( $record['blob_checksum'] ) || $record['blob_checksum'] != Tx_DrBlob_Div::calculateFileChecksum( $fileReference ) ) {
						$fp = fopen( $fileReference, 'r' );
							$record['blob_data'] = fread( $fp, filesize ( $fileReference ) );
						fclose( $fp );
						$record['blob_data'] = stripslashes( $record['blob_data'] );
						$record['is_quoted'] = true;
					} else {
						$record['is_quoted'] = false;
						$record['blob_data'] = $fileReference;
					}
				break;
				
				case '1': 
					$record['blob_data'] = stripslashes( $record['blob_data'] );
					$record['is_quoted'] = true;
				break;
			}
			
			
				//Increment the download counter
			$file->incrementDownloadCounter();
			$this->fileRepository->update( $file );

				//Perform download action
			if( $file->getRecordType() == 3 ) {
				$this->redirectToURI(  t3lib_div::locationHeaderUrl( Tx_DrBlob_Div::getUploadFolder() . 'storage/' . $record['blob_data'] ) );
			} else {
			
					//content related header
				$this->response->setHeader( 'Content-Type', $file->getFileMimeType(), true );
				$this->response->setHeader( 'Content-Length', $file->getFileSize(), true );
				$this->response->setHeader( 'Content-Transfer-Encoding', 'binary', true );
				$this->response->setHeader( 'Content-Disposition', ( (bool)$this->settings['tryToOpenFileInline'] ? 'attachment' : 'inline' ) . '; filename=' . $file->getFileName(), true );
				
					//caching related header
				$this->response->setHeader( 'Expires', gmdate( 'D, d M Y H:i:s', ( time()-3600 ) . ' GMT' ) );
				$this->response->setHeader( 'Last-Modified', gmdate( 'D, d M Y H:i:s', ( time()-3600 ) . ' GMT' ) );
				$this->response->setHeader( 'Cache-Control', 'post-check=0, pre-check=0' );
				$this->response->setHeader( 'Pragma', 'no-cache' );

					//Send out the headers
				$this->response->send();
				
					//if we run into a type=3-record, a redirect-header is already sent out, 
					//so the following lines won't be processed
					//if not, the file is either send to the client in one piece, or streamed in 
					//several pieces. The method used depends on whether the file is quoted- or not.
					//Quoted files cannot be streamed.
				if( $record['is_quoted'] ) {
					echo $record['blob_data'];
				} else {
					if( file_exists( $record['blob_data'] ) ) {
						$fp = fopen( $record['blob_data'], 'r' );
						while ( !feof( $fp ) ) {
							echo fread( $fp, self::BUFFER_LEN );
						}
						fclose( $fp );
					}
				}

					//Call the persistence manager to store the updated download counter
					//It won't be called by the Dispatcher because the render process is 
					//killed at the end of this method
				$persitMgr = Tx_Extbase_Dispatcher::getPersistenceManager();
				$persitMgr->persistAll();
				
					//prevend TYPO3 from rendering the page
				exit;
			}
		}
	}
	
	/**
	 * This method validates the response mime type of the file
	 * against to request mime type the client sent to the server
	 *
	 * @param string $mimeType The response mime type 
	 * @return bool whether the response mime type matches
	 */	
	private function validateResponseMimeType( $mimeType ) {
		$tmpRespMimeType = explode( '/', $mimeType );
		if( sizeof( $tmpRespMimeType ) == 2 ) {
			
				//Fill in the response content type to check
			$respMimeTypes = array(
				$tmpRespMimeType[0] . '/' . $tmpRespMimeType[1],
				$tmpRespMimeType[0] . '/*',
				'*/*'
			);
			
				//Fill in the request content type
			$tmpReqMimeType = explode( ',', $_SERVER['HTTP_ACCEPT'] );
			$reqMimeTypes = array();
			for( $i=0; $i < sizeof( $tmpReqMimeType ); $i++ ) {
				$reqMimeTypes[] = strrpos( $tmpReqMimeType[$i], ';' ) ? substr( $tmpReqMimeType[$i], 0, strrpos( $tmpReqMimeType[$i], ';' ) ) : $tmpReqMimeType[$i];
			}
			
				//validate the response against the request content type
			for( $i=0; $i < sizeof( $respMimeTypes ); $i++ ) {
				if( in_array( $respMimeTypes[$i], $reqMimeTypes ) ) {
					return true; 
				}
			}
			fclose( $fp );
		}
		return false;
	}
}
?>