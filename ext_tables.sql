# TYPO3 Extension Manager dump 1.1
#
# Host: 190.9.130.150    Database: typo3entwicklung
#--------------------------------------------------------


#
# Table structure for table "tx_drblob_content"
#
CREATE TABLE tx_drblob_content (
  uid int(11) NOT NULL auto_increment,
  pid int(11) NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted tinyint(4) unsigned NOT NULL default '0',
  hidden tinyint(4) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  fe_group int(11) unsigned NOT NULL default '0',
  title varchar(255) NOT NULL default '',
  description text NOT NULL,
  sort int(11) NOT NULL default '0',
  is_vip enum('0','1') NOT NULL default '0',
  blob_name varchar(255) NOT NULL default '',
  blob_size varchar(255) NOT NULL default '',
  blob_type varchar(255) NOT NULL default '',
  blob_data longblob NOT NULL,
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY title (title)
);