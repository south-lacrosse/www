SELECT CONCAT(IF(ID > 1, ',',  ''),
	'(',`ID`,",'", IF(ID = 1, 'admin', `user_login`),"','$P$BrQycETyoQX8Gn7JHrNUWD6LkE8Pvh/','",
	IF(ID = 1, 'admin', `user_nicename`),"','user",`ID`,"@southlacrosse.org.uk','",
	`user_url`,"','", `user_registered`,"','", `user_activation_key`, "',",
	`user_status`, ",'", IF(ID = 1, 'admin', `display_name`), "')"
) AS '' FROM `wp_users`;
