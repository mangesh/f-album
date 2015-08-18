--
-- Table structure for table `user`
--
CREATE TABLE IF NOT EXISTS `user` (
  `ID` int(12) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(70) DEFAULT NULL,
  `g_access_token` varchar(256) DEFAULT NULL,
  `refresh_token` varchar(250) DEFAULT NULL,
  `token_type` varchar(20) DEFAULT NULL,
  `expires_in` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;