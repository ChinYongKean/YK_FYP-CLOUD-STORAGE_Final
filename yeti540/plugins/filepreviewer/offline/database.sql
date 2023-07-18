DROP TABLE IF EXISTS `plugin_filepreviewer_background_thumb`;
CREATE TABLE `plugin_filepreviewer_background_thumb` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `thumb_status` enum('processing','failed','created','nonimage') COLLATE utf8_bin NOT NULL,
  `date_added` datetime NOT NULL,
  `processing_time` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


DROP TABLE IF EXISTS `plugin_filepreviewer_meta`;
CREATE TABLE `plugin_filepreviewer_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `width` int(8) NOT NULL,
  `height` int(8) NOT NULL,
  `raw_data` text COLLATE utf8_bin,
  `date_taken` datetime DEFAULT NULL,
  `image_colors` varchar(100) COLLATE utf8_bin DEFAULT NULL,
  `image_bg_color` varchar(7) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  KEY `image_bg_color` (`image_bg_color`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


DROP TABLE IF EXISTS `plugin_filepreviewer_watermark`;
CREATE TABLE `plugin_filepreviewer_watermark` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) CHARACTER SET latin1 NOT NULL,
  `image_content` blob NOT NULL,
  `category` varchar(20) CHARACTER SET latin1 NOT NULL DEFAULT 'images',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
