CREATE TABLE svp_marketing_rules (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			segment_key varchar(32) NOT NULL DEFAULT '',
			enabled tinyint(1) NOT NULL DEFAULT 0,
			priority int NOT NULL DEFAULT 100,
			cooldown_days int NOT NULL DEFAULT 90,
			after_days int NOT NULL DEFAULT 0,
			pending_hours int NOT NULL DEFAULT 0,
			funnel_idle_hours int NOT NULL DEFAULT 0,
			expires_within_days int NOT NULL DEFAULT 0,
			discount_type varchar(16) NOT NULL DEFAULT 'percent',
			discount_value decimal(15,2) NOT NULL DEFAULT 0,
			max_discount_toman decimal(15,2) DEFAULT NULL,
			code_valid_days int NOT NULL DEFAULT 7,
			max_uses_per_user int NOT NULL DEFAULT 1,
			message_body text NULL,
			channel_telegram tinyint(1) NOT NULL DEFAULT 1,
			channel_bale tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY owner_enabled (owner_svp_user_id, enabled),
			KEY segment (segment_key)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_marketing_offers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rule_id bigint(20) unsigned NOT NULL DEFAULT 0,
			svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			discount_code_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(16) NOT NULL DEFAULT 'issued',
			sent_at datetime DEFAULT NULL,
			converted_transaction_id bigint(20) unsigned NOT NULL DEFAULT 0,
			meta_json longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY rule_user (rule_id, svp_user_id),
			KEY user_status (svp_user_id, status),
			KEY discount_code_id (discount_code_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_discount_codes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			code varchar(64) NOT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			discount_type varchar(16) NOT NULL DEFAULT 'percent',
			discount_value decimal(15,2) NOT NULL DEFAULT 0,
			max_uses int DEFAULT NULL,
			uses_count int NOT NULL DEFAULT 0,
			valid_from datetime DEFAULT NULL,
			valid_until datetime DEFAULT NULL,
			min_order_toman decimal(15,2) DEFAULT NULL,
			allow_new_purchase tinyint(1) NOT NULL DEFAULT 1,
			allow_renew_same tinyint(1) NOT NULL DEFAULT 1,
			allow_add_volume tinyint(1) NOT NULL DEFAULT 1,
			allow_add_user_slots tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY owner_code (owner_svp_user_id, code),
			KEY active (active),
			KEY owner_svp_user (owner_svp_user_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_referral_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			inviter_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			platform varchar(16) NOT NULL DEFAULT '',
			visitor_chat_id bigint(20) NOT NULL DEFAULT 0,
			visitor_platform_user_id bigint(20) NOT NULL DEFAULT 0,
			start_payload varchar(128) NOT NULL DEFAULT '',
			outcome varchar(32) NOT NULL DEFAULT '',
			resulting_svp_user_id bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY (id),
			KEY inviter_created (inviter_svp_user_id, created_at),
			KEY created_at (created_at)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_user_activity (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			subject_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			channel varchar(16) NOT NULL DEFAULT 'rest',
			actor_kind varchar(20) NOT NULL DEFAULT 'system',
			actor_wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			platform_chat_id bigint(20) NOT NULL DEFAULT 0,
			event_type varchar(64) NOT NULL DEFAULT '',
			payload_json longtext NULL,
			PRIMARY KEY (id),
			KEY subject_id (subject_svp_user_id, id),
			KEY channel (channel),
			KEY created_at (created_at)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_service_ip_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_id bigint(20) unsigned NOT NULL,
			ip varchar(64) NOT NULL DEFAULT '',
			first_seen_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_seen_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			hit_count int unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY svc_ip (service_id, ip),
			KEY service_seen (service_id, last_seen_at)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_discount_redemptions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			discount_code_id bigint(20) unsigned NOT NULL DEFAULT 0,
			transaction_id bigint(20) unsigned NOT NULL DEFAULT 0,
			svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			subtotal_toman decimal(15,2) NOT NULL DEFAULT 0,
			discount_toman decimal(15,2) NOT NULL DEFAULT 0,
			volume_gb decimal(12,3) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY tx_once (transaction_id),
			KEY code_id (discount_code_id),
			KEY user_id (svp_user_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_unit_economics_config (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dev_ops_costs decimal(15,4) NOT NULL DEFAULT 0,
			outbound_cost_per_gb decimal(15,4) NOT NULL DEFAULT 0,
			cdn_cost_per_gb decimal(15,4) NOT NULL DEFAULT 0,
			total_sold_volume_gb decimal(15,4) NOT NULL DEFAULT 0,
			selling_price_per_gb decimal(15,4) NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_unit_economics_servers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(128) NOT NULL DEFAULT '',
			cost_amount decimal(15,4) NOT NULL DEFAULT 0,
			billing_cycle varchar(16) NOT NULL DEFAULT 'monthly',
			sort_order int NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY sort_order (sort_order)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_panel_economics_lines (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 0,
			category varchar(32) NOT NULL DEFAULT 'external_server',
			label varchar(128) NOT NULL DEFAULT '',
			provider varchar(128) NOT NULL DEFAULT '',
			cost_amount decimal(15,4) NOT NULL DEFAULT 0,
			billing_cycle varchar(16) NOT NULL DEFAULT 'monthly',
			payment_method varchar(64) NOT NULL DEFAULT '',
			paid_at date DEFAULT NULL,
			expires_at date DEFAULT NULL,
			host_ip varchar(64) NOT NULL DEFAULT '',
			tunnel_mode varchar(64) NOT NULL DEFAULT '',
			notes text NULL,
			sort_order int NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY panel_category (panel_id, category),
			KEY panel_sort (panel_id, sort_order)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_reseller_inbound_display_names (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reseller_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 0,
			inbound_id int(11) NOT NULL DEFAULT 0,
			label varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			UNIQUE KEY reseller_panel_inbound (reseller_svp_user_id, panel_id, inbound_id),
			KEY panel_inbound (panel_id, inbound_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_reseller_panel_prices (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reseller_svp_user_id bigint(20) unsigned NOT NULL,
			panel_id bigint(20) unsigned NOT NULL,
			price_per_gb decimal(15,4) NOT NULL DEFAULT 0,
			panel_access tinyint(1) NOT NULL DEFAULT 1,
			default_service_type varchar(16) NOT NULL DEFAULT 'xray',
			default_inbound_id int NOT NULL DEFAULT 0,
			default_l2tp_server_id bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY reseller_panel (reseller_svp_user_id, panel_id),
			KEY panel_id (panel_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_reseller_parent_panel_floors (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			parent_svp_user_id bigint(20) unsigned NOT NULL,
			child_svp_user_id bigint(20) unsigned NOT NULL,
			panel_id bigint(20) unsigned NOT NULL,
			min_price_per_gb decimal(15,4) NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY parent_child_panel (parent_svp_user_id, child_svp_user_id, panel_id),
			KEY child_panel (child_svp_user_id, panel_id),
			KEY parent_child (parent_svp_user_id, child_svp_user_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_reseller_bot_profiles (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reseller_svp_user_id bigint(20) unsigned NOT NULL,
			telegram_token text NULL,
			bale_token text NULL,
			webhook_secret varchar(128) NOT NULL DEFAULT '',
			brand_name varchar(255) NOT NULL DEFAULT '',
			logo_url varchar(512) NOT NULL DEFAULT '',
			favicon_url varchar(512) NOT NULL DEFAULT '',
			theme_primary varchar(16) NOT NULL DEFAULT '',
			theme_accent varchar(16) NOT NULL DEFAULT '',
			custom_domain varchar(255) NOT NULL DEFAULT '',
			telegram_relay_public_url varchar(255) NOT NULL DEFAULT '',
			config_label_override varchar(255) NOT NULL DEFAULT '',
			config_label_prefix varchar(255) NOT NULL DEFAULT '',
			telegram_secret_token varchar(255) NOT NULL DEFAULT '',
			enabled tinyint(1) NOT NULL DEFAULT 1,
			telegram_enabled tinyint(1) NOT NULL DEFAULT 1,
			bale_enabled tinyint(1) NOT NULL DEFAULT 1,
			admin_telegram_ids longtext NULL,
			admin_bale_ids longtext NULL,
			bale_wallet_provider_token varchar(255) NOT NULL DEFAULT '',
			telegram_bot_username varchar(128) NOT NULL DEFAULT '',
			bale_bot_username varchar(128) NOT NULL DEFAULT '',
			text_overrides_json longtext NULL,
			payment_methods_json longtext NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY reseller_one (reseller_svp_user_id),
			KEY custom_domain (custom_domain)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_reseller_closure (
			ancestor_id bigint(20) unsigned NOT NULL,
			descendant_id bigint(20) unsigned NOT NULL,
			depth smallint unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (ancestor_id, descendant_id),
			KEY descendant (descendant_id),
			KEY ancestor_depth (ancestor_id, depth)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_audit_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			domain varchar(32) NOT NULL DEFAULT 'admin',
			event_type varchar(64) NOT NULL DEFAULT '',
			actor_kind varchar(20) NOT NULL DEFAULT 'system',
			actor_wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target_type varchar(32) NOT NULL DEFAULT '',
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reseller_scope_id bigint(20) unsigned NOT NULL DEFAULT 0,
			payload_json longtext NULL,
			ip_hash char(64) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY domain_created (domain, created_at),
			KEY event_type (event_type),
			KEY target (target_type, target_id),
			KEY actor_svp (actor_svp_user_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_panel_inbound_clients (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			panel_id bigint(20) unsigned NOT NULL,
			inbound_id int NOT NULL,
			inbound_remark varchar(255) NOT NULL DEFAULT '',
			protocol varchar(32) NOT NULL DEFAULT '',
			port int NOT NULL DEFAULT 0,
			email varchar(191) NOT NULL,
			xui_client_id varchar(191) NOT NULL DEFAULT '',
			remark varchar(255) NOT NULL DEFAULT '',
			comment varchar(500) NOT NULL DEFAULT '',
			tg_id varchar(64) NOT NULL DEFAULT '',
			sub_id varchar(128) NOT NULL DEFAULT '',
			enable tinyint(1) NOT NULL DEFAULT 1,
			total_gb int NOT NULL DEFAULT 0,
			expiry_ms bigint NOT NULL DEFAULT 0,
			used_bytes bigint NOT NULL DEFAULT 0,
			limit_bytes bigint NOT NULL DEFAULT 0,
			is_online tinyint(1) NOT NULL DEFAULT 0,
			client_ips_json longtext NULL,
			client_json longtext NULL,
			synced_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY p_i_e (panel_id, inbound_id, email),
			KEY panel_i (panel_id, inbound_id),
			KEY panel_synced (panel_id, synced_at)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_panel_inbound_api (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			panel_id bigint(20) unsigned NOT NULL,
			inbound_id int NOT NULL,
			inbound_json longtext NOT NULL,
			synced_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY p_in (panel_id, inbound_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_services (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 1,
			inbound_id int NOT NULL,
			inbound_ids longtext NULL,
			xui_client_id varchar(191) DEFAULT NULL,
			xui_client_uuid varchar(64) DEFAULT NULL,
			email varchar(191) NOT NULL,
			remark varchar(255) DEFAULT '',
			display_label varchar(255) NOT NULL DEFAULT '',
			service_note varchar(512) DEFAULT '',
			plan_id bigint(20) unsigned DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			total_traffic bigint NOT NULL DEFAULT 0,
			used_traffic bigint NOT NULL DEFAULT 0,
			autorenew tinyint(1) NOT NULL DEFAULT 0,
			alerts_enabled tinyint(1) NOT NULL DEFAULT 1,
			alerts_volume tinyint(1) NOT NULL DEFAULT 1,
			alerts_expiry tinyint(1) NOT NULL DEFAULT 1,
			alerts_users tinyint(1) NOT NULL DEFAULT 1,
			alert_low_pct smallint DEFAULT NULL,
			alert_expiry_days varchar(64) DEFAULT NULL,
			alert_ip_fill_pct smallint DEFAULT NULL,
			alert_schedule_json longtext NULL,
			last_warn_sent_at datetime DEFAULT NULL,
			panel_limit_ip int unsigned DEFAULT NULL,
			panel_client_enabled tinyint(1) DEFAULT NULL,
			sub_id varchar(128) DEFAULT NULL,
			provision_type varchar(20) NOT NULL DEFAULT 'plan',
			service_type varchar(16) NOT NULL DEFAULT 'xray',
			l2tp_server_id bigint(20) unsigned DEFAULT NULL,
			l2tp_username varchar(64) DEFAULT NULL,
			l2tp_password_enc text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY email (email),
			KEY inbound_id (inbound_id),
			KEY service_type (service_type),
			KEY l2tp_username (l2tp_username),
			KEY deleted_at (deleted_at)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_l2tp_servers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(128) NOT NULL DEFAULT '',
			ssh_host varchar(191) NOT NULL DEFAULT '',
			ssh_port int NOT NULL DEFAULT 22,
			ssh_user varchar(64) NOT NULL DEFAULT '',
			ssh_auth varchar(16) NOT NULL DEFAULT 'key',
			ssh_password_enc text NULL,
			ssh_private_key_enc longtext NULL,
			ssh_key_passphrase_enc text NULL,
			l2tp_host varchar(191) NOT NULL DEFAULT '',
			l2tp_psk_enc text NULL,
			chap_path varchar(255) NOT NULL DEFAULT '/etc/ppp/chap-secrets',
			reload_cmd varchar(255) NOT NULL DEFAULT 'sudo /bin/systemctl reload xl2tpd',
			usage_cmd_template text NULL,
			apps_note text NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_plans (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			category varchar(32) NOT NULL DEFAULT 'normal',
			duration_days int NOT NULL DEFAULT 30,
			traffic_gb bigint NOT NULL DEFAULT 0,
			price decimal(15,2) NOT NULL DEFAULT 0,
			pricing_type varchar(20) NOT NULL DEFAULT 'fixed',
			quota_display_mode varchar(20) NOT NULL DEFAULT 'show',
			price_per_gb decimal(15,2) NOT NULL DEFAULT 0,
			traffic_gb_min int NOT NULL DEFAULT 0,
			traffic_gb_max int NOT NULL DEFAULT 0,
			clients_count int NOT NULL DEFAULT 1,
			inbound_id int NOT NULL DEFAULT 0,
			inbound_ids longtext NULL,
			panel_template_id int unsigned DEFAULT NULL,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 1,
			wholesale_line_id bigint(20) unsigned DEFAULT NULL,
			service_type varchar(16) NOT NULL DEFAULT 'xray',
			l2tp_server_id bigint(20) unsigned DEFAULT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int NOT NULL DEFAULT 0,
			owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY category (category),
			KEY active (active),
			KEY service_type (service_type),
			KEY panel_id (panel_id),
			KEY wholesale_line_id (wholesale_line_id),
			KEY owner_svp_user (owner_svp_user_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_plan_categories (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 1,
			slug varchar(32) NOT NULL,
			label varchar(191) NOT NULL,
			sort_order int NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY panel_slug (panel_id, slug),
			KEY active (active),
			KEY panel_id (panel_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_panels (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(191) NOT NULL DEFAULT '',
			panel_url text NOT NULL,
			panel_username varchar(191) NOT NULL DEFAULT '',
			panel_password text NOT NULL,
			panel_api_base varchar(191) NOT NULL DEFAULT 'panel/api',
			panel_login_secret varchar(255) NOT NULL DEFAULT '',
			panel_api_token text NULL,
			panel_api_flavor varchar(32) NOT NULL DEFAULT 'unknown',
			panel_provider varchar(32) NOT NULL DEFAULT 'xui',
			panel_template_required tinyint(1) NOT NULL DEFAULT 0,
			subscription_public_base text NULL,
			sort_order int NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_panel_online_daily (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			panel_id bigint(20) unsigned NOT NULL,
			stat_date date NOT NULL,
			max_online int NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY panel_stat (panel_id, stat_date),
			KEY stat_date (stat_date)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_monitor_hosts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(191) NOT NULL DEFAULT '',
			metrics_url text NOT NULL,
			bearer_token varchar(512) NOT NULL DEFAULT '',
			sort_order int NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_users (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tg_user_id bigint(20) DEFAULT NULL,
			bale_user_id bigint(20) DEFAULT NULL,
			first_name varchar(191) DEFAULT '',
			last_name varchar(191) DEFAULT '',
			username varchar(191) DEFAULT '',
			phone varchar(32) DEFAULT '',
			role varchar(20) NOT NULL DEFAULT 'user',
			balance decimal(15,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			approved_by varchar(64) DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			admin_mode tinyint(1) NOT NULL DEFAULT 0,
			state varchar(64) DEFAULT NULL,
			state_data longtext NULL,
			bot_locale varchar(5) NOT NULL DEFAULT '',
			invited_by bigint(20) unsigned DEFAULT NULL,
			signup_reseller_svp_id bigint(20) unsigned DEFAULT NULL,
			last_tg_mirror_bot_id bigint(20) unsigned NOT NULL DEFAULT 0,
			wp_user_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY svp_users_tg (tg_user_id),
			UNIQUE KEY svp_users_bale (bale_user_id),
			UNIQUE KEY svp_users_wp (wp_user_id),
			KEY status (status),
			KEY role (role),
			KEY invited_by (invited_by),
			KEY signup_reseller (signup_reseller_svp_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_cards (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			card_number varchar(32) NOT NULL,
			holder_name varchar(191) NOT NULL,
			bank_name varchar(64) NOT NULL DEFAULT '',
			method_key varchar(16) NOT NULL DEFAULT 'c2c',
			daily_limit decimal(15,2) NOT NULL DEFAULT 0,
			priority int NOT NULL DEFAULT 0,
			note text NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active),
			KEY owner_svp_user (owner_svp_user_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_transactions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned DEFAULT NULL,
			amount decimal(15,2) NOT NULL,
			type varchar(32) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			meta_json longtext NULL,
			billing_reseller_svp_id bigint(20) unsigned DEFAULT NULL,
			referral_amount decimal(15,2) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY type (type),
			KEY billing_reseller_svp_id (billing_reseller_svp_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_receipts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			transaction_id bigint(20) unsigned NOT NULL,
			tg_file_id varchar(191) DEFAULT '',
			bale_file_id varchar(191) DEFAULT '',
			stored_image_path varchar(512) NOT NULL DEFAULT '',
			amount decimal(15,2) NOT NULL,
			card_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			admin_messages_json longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			decided_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY transaction_id (transaction_id),
			KEY status (status)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_pending_approvals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			bot varchar(8) NOT NULL,
			admin_messages_json longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			decided_at datetime DEFAULT NULL,
			decided_by varchar(64) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_sync_codes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			code varchar(16) NOT NULL,
			generated_bot varchar(8) NOT NULL,
			consumed tinyint(1) NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY code (code),
			KEY user_id (user_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_texts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			key_name varchar(191) NOT NULL,
			category varchar(64) NOT NULL DEFAULT 'general',
			locale varchar(5) NOT NULL DEFAULT 'fa',
			value longtext NOT NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY svp_texts_key_locale (key_name, locale),
			KEY category (category)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_broadcasts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(16) NOT NULL,
			content longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			sent_count int NOT NULL DEFAULT 0,
			failed_count int NOT NULL DEFAULT 0,
			total_targets int NOT NULL DEFAULT 0,
			blocked_count int NOT NULL DEFAULT 0,
			meta_json longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY owner_svp_user (owner_svp_user_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_broadcast_queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			broadcast_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			bot varchar(8) NOT NULL,
			chat_id bigint(20) NOT NULL,
			payload_json longtext NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'pending',
			tries int NOT NULL DEFAULT 0,
			last_error text NULL,
			failure_kind varchar(32) NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY broadcast_id (broadcast_id),
			KEY status (status)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_users_bulk_jobs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation varchar(32) NOT NULL,
			scope varchar(32) NOT NULL DEFAULT 'all_approved',
			payload_json longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_by_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			finished_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_by_svp (created_by_svp_user_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_users_bulk_job_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 0,
			inbound_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_email varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			tries int NOT NULL DEFAULT 0,
			last_error text NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY job_id (job_id),
			KEY status (status),
			KEY job_user (job_id, user_id),
			KEY job_panel_client (job_id, panel_id, inbound_id, client_email(120))
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(16) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context_json longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY level (level),
			KEY created_at (created_at)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_reseller_wholesale_lines (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(191) NOT NULL DEFAULT '',
			badge_color varchar(32) NOT NULL DEFAULT '',
			panel_id bigint(20) unsigned NOT NULL DEFAULT 1,
			default_service_type varchar(16) NOT NULL DEFAULT 'xray',
			default_inbound_id int NOT NULL DEFAULT 0,
			default_l2tp_server_id bigint(20) unsigned NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY panel_id (panel_id),
			KEY active_sort (active, sort_order)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_reseller_wholesale_tiers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			line_id bigint(20) unsigned NOT NULL,
			sort_order int NOT NULL DEFAULT 0,
			price_per_gb decimal(15,4) NOT NULL DEFAULT 0,
			min_total_gb bigint NOT NULL DEFAULT 0,
			min_total_toman decimal(15,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY line_sort (line_id, sort_order)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_reseller_wholesale_line_assignments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reseller_svp_user_id bigint(20) unsigned NOT NULL,
			line_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY reseller_line (reseller_svp_user_id, line_id),
			KEY line_id (line_id)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_reseller_wholesale_accruals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reseller_svp_user_id bigint(20) unsigned NOT NULL,
			line_id bigint(20) unsigned NOT NULL,
			delta_gb bigint NOT NULL DEFAULT 0,
			delta_wholesale_toman decimal(15,2) NOT NULL DEFAULT 0,
			unit_price_applied decimal(15,4) NOT NULL DEFAULT 0,
			transaction_id bigint(20) unsigned DEFAULT NULL,
			service_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY u_tx (transaction_id),
			KEY reseller_line (reseller_svp_user_id, line_id),
			KEY created (created_at)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_inbound_queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			platform varchar(8) NOT NULL,
			reseller_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mirror_bot_id bigint(20) unsigned NOT NULL DEFAULT 0,
			update_json longtext NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'pending',
			tries int NOT NULL DEFAULT 0,
			last_error text NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status_created (status, created_at)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE svp_telegram_mirror_bots (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(255) NOT NULL DEFAULT '',
			telegram_token text NULL,
			telegram_bot_username varchar(128) NOT NULL DEFAULT '',
			webhook_secret varchar(512) NOT NULL DEFAULT '',
			telegram_secret_token varchar(255) NOT NULL DEFAULT '',
			enabled tinyint(1) NOT NULL DEFAULT 1,
			sort_order int NOT NULL DEFAULT 0,
			updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY enabled_sort (enabled, sort_order)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
