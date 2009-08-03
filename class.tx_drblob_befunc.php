<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005-present Daniel Regelein (Daniel.Regelein@diehl-informatik.de)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * @name 		tx_drblob_befunc
 * Class being included by t3lib_befunc using the hook displayWarningMessages_postProcess
 * 
 * @author		Daniel Regelein <Daniel.Regelein@diehl-informatik.de>
 * @package 	dr_blob
 * @filesource	class.tx_drblob_tcemain.php
 * @version		2.0.1
 * @since 		2.0.1, 2009-02-24
 */
class tx_drblob_befunc {
	var $defaultUploadFolder = 'uploads/tx_drblob/storage/';
	
	
	/**
	 * Display some warning messages if this installation is obviously insecure!!
	 * These warnings are only displayed to admin users
	 *
	 * @return	void
	 */
	function displayWarningMessages_postProcess( &$warning ) {

		$extConf = unserialize( $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['dr_blob'] );

		if( $extConf['fileStorageLocation'] == 'Both' || $extConf['fileStorageLocation'] == 'FileSystem' ) {
		
			$folder = $extConf['fileStorageFolder'] ? $extConf['fileStorageFolder'] : $this->defaultUploadFolder;

			
			$warning['tx_drblob_uploadFolderNotWriteable'] = sprintf(
				$GLOBALS['LANG']->sL( 'LLL:EXT:dr_blob/locallang_wiz.xml:err_uploadFolderNotWriteable' ),
				$folder
			);
			
			//Check whether the storage folder exists...
			if( @is_dir( $folder ) ) {
				
				//... and is writeable
				if( @is_writeable( $folder ) ) {
					unset( $warning['tx_drblob_uploadFolderNotWriteable'] );
				}

				
				//Check whether the storage folder is accessible via web
				if( t3lib_div::isFirstPartOfStr( $folder, PATH_site ) ) {
					$warning['tx_drblob_uploadFolderAccesibleViaWeb'] = sprintf(
						$GLOBALS['LANG']->sL( 'LLL:EXT:dr_blob/locallang_wiz.xml:err_uploadFolderAccesibleViaWeb' ),
						$folder
					);
				}
			}
			
		}
	}
	
	
	/**
	 * @name		__toString
	 * Output the class makes when calling <code>echo $obj;</code>
	 * 
	 * @access		public
	 * @return		String		"tx_drblob_befunc"
	 */
	/*public*/function __toString() {
		return 'tx_drblob_befunc';
	}
};


if ( defined( 'TYPO3_MODE' ) && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dr_blob/class.tx_drblob_befunc.php'] ) {
    include_once( $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dr_blob/class.tx_drblob_befunc.php'] );
}
?>