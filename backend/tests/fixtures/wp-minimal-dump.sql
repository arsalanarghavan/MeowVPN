-- Minimal WordPress dump for wp:import tests (prefix wp_)

INSERT INTO `wp_svp_users` (`id`, `username`, `role`, `status`, `tg_user_id`, `wp_user_id`, `created_at`) VALUES
(1, 'user1', 'user', 'approved', 900001, NULL, '2024-01-01 00:00:00');

INSERT INTO `wp_svp_users` (`id`, `username`, `role`, `status`, `wp_user_id`, `created_at`) VALUES
(100, 'reseller1', 'reseller', 'approved', 2, '2024-01-01 00:00:00');

INSERT INTO `wp_svp_services` (`id`, `user_id`, `panel_id`, `email`, `total_traffic`, `used_traffic`, `created_at`) VALUES
(1, 1, 1, 'u1@test.local', 0, 0, '2024-01-01 00:00:00');

INSERT INTO `wp_options` (`option_id`, `option_name`, `option_value`, `autoload`) VALUES
(1, 'simplevpbot_settings', 'a:2:{s:7:"enabled";b:1;s:18:"telegram_bot_token";s:16:"secret-token-123";}', 'yes');

INSERT INTO `wp_options` (`option_id`, `option_name`, `option_value`, `autoload`) VALUES
(2, 'simplevpbot_reseller_perms_100', 'a:1:{s:12:"users.manage";b:1;}', 'yes');

INSERT INTO `wp_users` (`ID`, `user_login`, `user_pass`, `user_email`, `user_registered`) VALUES
(1, 'wpadmin', '$P$Bxx', 'admin@test.local', '2024-01-01 00:00:00');

INSERT INTO `wp_users` (`ID`, `user_login`, `user_pass`, `user_email`, `user_registered`) VALUES
(2, 'reseller1', '$P$Bxx', 'reseller@test.local', '2024-01-01 00:00:00');

INSERT INTO `wp_usermeta` (`umeta_id`, `user_id`, `meta_key`, `meta_value`) VALUES
(1, 1, 'wp_capabilities', 'a:1:{s:13:"administrator";b:1;}');

INSERT INTO `wp_usermeta` (`umeta_id`, `user_id`, `meta_key`, `meta_value`) VALUES
(2, 1, 'svp_dashboard_accent', 'blue');

INSERT INTO `wp_usermeta` (`umeta_id`, `user_id`, `meta_key`, `meta_value`) VALUES
(3, 2, 'svp_dashboard_theme', 'dark');
