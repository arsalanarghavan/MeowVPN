<?php

namespace App\Modules\Core\Bot\Services;

use App\Services\SettingsStore;

/**
 * Bot UI action registry (WP SimpleVPBot_UI_Action_Registry parity).
 */
class UiActionRegistryService {

	const LAYOUT_VERSION = 1;

	/**
	 * All UI actions keyed by stable id.
	 *
	 * route keys:
	 * - hub: submenu code for Handler_Admin_Pnl::send_submenu
	 * - wizard: "a:b" for Handler_Admin_Settings::start_wizard
	 * - settings_op: single letter code for Handler_Admin_Settings::handle_op
	 * - admin_route: key handled in Handler_Admin::route_text (legacy dispatch key)
	 * - user_main: dispatch via Handler_User_Menu
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function actions() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache = array_merge(
			self::user_main_actions(),
			self::admin_main_actions(),
			self::admin_submenu_actions(),
			self::hub_catalog_actions(),
			self::hub_advanced_actions(),
			self::wizard_and_ops_actions(),
			self::admin_misc_actions(),
			self::svc_xray_slots(),
			self::svc_l2tp_slots(),
			self::purchase_delivery_slots()
		);
		return $cache;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function user_main_actions() {
		return array(
			'user.main.buy'     => array(
				'surface'       => 'user_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.main.buy',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'user_main' => 'buy' ),
			),
			'user.main.manage'  => array(
				'surface'       => 'user_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.main.manage',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'user_main' => 'manage' ),
			),
			'user.main.apps'    => array(
				'surface'       => 'user_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.main.apps',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'user_main' => 'apps' ),
			),
			'user.main.support' => array(
				'surface'       => 'user_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.main.support',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'user_main' => 'support' ),
			),
			'user.main.account' => array(
				'surface'       => 'user_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.main.account',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'user_main' => 'account' ),
			),
			'user.main.wallet'  => array(
				'surface'       => 'user_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.main.wallet',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'user_main' => 'wallet' ),
			),
			'user.main.referral' => array(
				'surface'       => 'user_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.main.referral',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'user_main' => 'referral' ),
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function admin_main_actions() {
		return array(
			'admin.root.dashboard' => array(
				'surface'       => 'admin_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.dashboard',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'admin_route' => 'dashboard' ),
			),
			'admin.root.users'     => array(
				'surface'       => 'admin_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.users',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'admin_route' => 'users' ),
			),
			'admin.root.finance'   => array(
				'surface'       => 'admin_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.finance',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'admin_route' => 'finance' ),
			),
			'admin.root.settings'  => array(
				'surface'       => 'admin_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.settings',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '⚙️ تنظیمات ربات' ),
				'route'         => array( 'admin_route' => 'settings' ),
			),
			'admin.root.advanced'  => array(
				'surface'       => 'admin_main',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.advanced',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'admin_route' => 'advanced' ),
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function admin_submenu_actions() {
		return array(
			'admin.users.search'   => array(
				'surface'       => 'admin_users_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.users_search',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'admin_route' => 'users_search' ),
			),
			'admin.users.queue'    => array(
				'surface'       => 'admin_users_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.users_queue',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'admin_route' => 'users_queue' ),
			),
			'admin.users.transfer' => array(
				'surface'       => 'admin_users_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.transfer',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'admin_route' => 'transfer' ),
			),
			'admin.users.bulk'     => array(
				'surface'       => 'admin_users_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.bulk_short',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '➕ گروهی' ),
				'route'         => array( 'panel_tab' => 'users_bulk' ),
			),
			'admin.users.broadcast' => array(
				'surface'       => 'admin_users_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.broadcast',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'admin_route' => 'broadcast' ),
			),
			'admin.finance.receipts' => array(
				'surface'       => 'admin_finance_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.receipts',
				'glass_default' => false,
				'max_len'       => 256,
				'route'         => array( 'admin_route' => 'receipts' ),
			),
		);
	}

	/**
	 * Settings catalog grid (was hardcoded emoji rows).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function hub_catalog_actions() {
		return array(
			'admin.cat.plan_cats' => array(
				'surface'       => 'admin_settings_catalog',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.cat.plan_cats',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📂 دسته پلن' ),
				'route'         => array( 'panel_tab' => 'plan_cats' ),
			),
			'admin.cat.plans'     => array(
				'surface'       => 'admin_settings_catalog',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.cat.plans',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📋 پلن‌ها' ),
				'route'         => array( 'panel_tab' => 'plans' ),
			),
			'admin.cat.cards'   => array(
				'surface'       => 'admin_settings_catalog',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.cat.cards',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '💳 کارت‌ها' ),
				'route'         => array( 'panel_tab' => 'cards' ),
			),
			'admin.cat.panel'   => array(
				'surface'       => 'admin_settings_catalog',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.cat.panel',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '🖥 پنل' ),
				'route'         => array( 'panel_tab' => 'xui_panels' ),
			),
			'admin.cat.l2tp'    => array(
				'surface'       => 'admin_settings_catalog',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.cat.l2tp',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '🔌 L2TP' ),
				'route'         => array( 'panel_tab' => 'l2tp_servers' ),
			),
			'admin.cat.config'  => array(
				'surface'       => 'admin_settings_catalog',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.cat.config',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '🔗 کانفیگ' ),
				'route'         => array( 'panel_tab' => 'configs' ),
			),
			'admin.cat.crypto'  => array(
				'surface'       => 'admin_settings_catalog',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.cat.crypto',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '₿ کریپتو' ),
				'route'         => array( 'pnl_submenu' => 'pay' ),
			),
			'admin.cat.bots'    => array(
				'surface'       => 'admin_settings_catalog',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.cat.bots',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '🤖 ربات‌ها' ),
				'route'         => array( 'panel_tab' => 'bots' ),
			),
		);
	}

	/**
	 * Advanced settings submenu.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function hub_advanced_actions() {
		return array(
			'admin.adv.backup'    => array(
				'surface'       => 'admin_settings_advanced',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.backup',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '💾 بکاپ' ),
				'route'         => array( 'admin_route' => 'backup' ),
			),
			'admin.adv.general'   => array(
				'surface'       => 'admin_settings_advanced',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.adv.general',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '⚙️ عمومی' ),
				'route'         => array( 'panel_tab' => 'site_settings' ),
			),
			'admin.adv.notif'     => array(
				'surface'       => 'admin_settings_advanced',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.adv.notif',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '🔔 نوتیف' ),
				'route'         => array( 'panel_tab' => 'notifications' ),
			),
			'admin.adv.texts'     => array(
				'surface'       => 'admin_settings_advanced',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.adv.texts',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📝 متن‌ها' ),
				'route'         => array( 'panel_tab' => 'texts' ),
			),
			'admin.adv.logs'      => array(
				'surface'       => 'admin_settings_advanced',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.adv.logs',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📜 لاگ' ),
				'route'         => array( 'panel_tab' => 'logs' ),
			),
			'admin.adv.broadcast' => array(
				'surface'       => 'admin_settings_advanced',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.adv.broadcast',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📣 گزارش همگانی' ),
				'route'         => array( 'panel_tab' => 'broadcast' ),
			),
		);
	}

	/**
	 * General / bot / panel / notif / crypto wizards + bot ops.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function wizard_and_ops_actions() {
		return array(
			'wiz.gen.at'          => array(
				'surface'       => 'admin_general_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.gen_at',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📥 ادمین TG' ),
				'route'         => array( 'wizard' => 'gen:at' ),
			),
			'wiz.gen.ab'          => array(
				'surface'       => 'admin_general_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.gen_ab',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📥 ادمین Bl' ),
				'route'         => array( 'wizard' => 'gen:ab' ),
			),
			'wiz.gen.pp'          => array(
				'surface'       => 'admin_general_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.gen_pp',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📄 ID پورتال' ),
				'route'         => array( 'wizard' => 'gen:pp' ),
			),
			'wiz.gen.dp'          => array(
				'surface'       => 'admin_general_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.gen_dp',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📦 پلن پیش‌فرض سرویس' ),
				'route'         => array( 'wizard' => 'gen:dp' ),
			),
			'wiz.bot.tt'          => array(
				'surface'       => 'admin_bot_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.bot_tt',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'tok TG' ),
				'route'         => array( 'wizard' => 'bot:tt' ),
			),
			'wiz.bot.bt'          => array(
				'surface'       => 'admin_bot_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.bot_bt',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'tok Bl' ),
				'route'         => array( 'wizard' => 'bot:bt' ),
			),
			'wiz.bot.ts'          => array(
				'surface'       => 'admin_bot_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.bot_ts',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'wh sec TG' ),
				'route'         => array( 'wizard' => 'bot:ts' ),
			),
			'wiz.bot.bs'          => array(
				'surface'       => 'admin_bot_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.bot_bs',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'wh sec Bl' ),
				'route'         => array( 'wizard' => 'bot:bs' ),
			),
			'wiz.bot.th'          => array(
				'surface'       => 'admin_bot_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.bot_th',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'hdr' ),
				'route'         => array( 'wizard' => 'bot:th' ),
			),
			'wiz.bot.bw'          => array(
				'surface'       => 'admin_bot_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.bot_bw',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'Bale $' ),
				'route'         => array( 'wizard' => 'bot:bw' ),
			),
			'op.bot.getme'        => array(
				'surface'       => 'admin_bot_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.op.getme',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'getMe' ),
				'route'         => array( 'settings_op' => 'tg' ),
			),
			'op.bot.wh_tg'        => array(
				'surface'       => 'admin_bot_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.op.wh_tg',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'Set WH TG', '🔗 تنظیم وب‌هوک تلگرام' ),
				'route'         => array( 'settings_op' => 'wtg' ),
			),
			'op.bot.wh_bl'        => array(
				'surface'       => 'admin_bot_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.op.wh_bl',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'Set WH Bl', '🔗 تنظیم وب‌هوک بله' ),
				'route'         => array( 'settings_op' => 'wbl' ),
			),
			'wiz.pan.u'           => array(
				'surface'       => 'admin_panel_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.pan_u',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'URL' ),
				'route'         => array( 'wizard' => 'pan:u' ),
			),
			'wiz.pan.n'           => array(
				'surface'       => 'admin_panel_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.pan_n',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'User' ),
				'route'         => array( 'wizard' => 'pan:n' ),
			),
			'wiz.pan.p'           => array(
				'surface'       => 'admin_panel_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.pan_p',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'Pass' ),
				'route'         => array( 'wizard' => 'pan:p' ),
			),
			'wiz.pan.a'           => array(
				'surface'       => 'admin_panel_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.pan_a',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'API' ),
				'route'         => array( 'wizard' => 'pan:a' ),
			),
			'wiz.pan.l'           => array(
				'surface'       => 'admin_panel_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.pan_l',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'Log sec' ),
				'route'         => array( 'wizard' => 'pan:l' ),
			),
			'wiz.pan.s'           => array(
				'surface'       => 'admin_panel_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.pan_s',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'Sub URL' ),
				'route'         => array( 'wizard' => 'pan:s' ),
			),
			'op.pan.test'         => array(
				'surface'       => 'admin_panel_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.op.pan_test',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '🔬 تست اتصال' ),
				'route'         => array( 'settings_op' => 'pan' ),
			),
			'wiz.not.l'           => array(
				'surface'       => 'admin_notif_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.not_l',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '٪ کمی' ),
				'route'         => array( 'wizard' => 'not:l' ),
			),
			'wiz.not.e'           => array(
				'surface'       => 'admin_notif_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.not_e',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'روز هشدار' ),
				'route'         => array( 'wizard' => 'not:e' ),
			),
			'wiz.not.d'           => array(
				'surface'       => 'admin_notif_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.not_d',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'سقف کاربر' ),
				'route'         => array( 'wizard' => 'not:d' ),
			),
			'wiz.not.p'           => array(
				'surface'       => 'admin_notif_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.not_p',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( 'قیمت+کاربر' ),
				'route'         => array( 'wizard' => 'not:p' ),
			),
			'wiz.cry.ak'          => array(
				'surface'       => 'admin_crypto_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.cry_ak',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '₿ API' ),
				'route'         => array( 'wizard' => 'cry:ak' ),
			),
			'wiz.cry.in'          => array(
				'surface'       => 'admin_crypto_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.cry_in',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '₿ IPN' ),
				'route'         => array( 'wizard' => 'cry:in' ),
			),
			'wiz.cry.cu'          => array(
				'surface'       => 'admin_crypto_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.wiz.cry_cu',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '₿ Cur' ),
				'route'         => array( 'wizard' => 'cry:cu' ),
			),
			'hub.crypto.ipn_path' => array(
				'surface'       => 'admin_crypto_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.hub.crypto_ipn_path',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '🔄 مسیر IPN' ),
				'route'         => array( 'hub_dispatch_cb' => 'pnl:crx' ),
			),
			'bulk.days.1'         => array(
				'surface'       => 'admin_bulk_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.bulk.d1',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '+۱ روز' ),
				'route'         => array( 'bulk_days' => 1 ),
			),
			'bulk.days.7'         => array(
				'surface'       => 'admin_bulk_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.bulk.d7',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '+۷ روز' ),
				'route'         => array( 'bulk_days' => 7 ),
			),
			'bulk.days.30'        => array(
				'surface'       => 'admin_bulk_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.bulk.d30',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '+۳۰ روز' ),
				'route'         => array( 'bulk_days' => 30 ),
			),
			'bulk.gb.1'           => array(
				'surface'       => 'admin_bulk_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.bulk.g1',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '+۱ GB' ),
				'route'         => array( 'bulk_gb' => 1 ),
			),
			'bulk.gb.5'           => array(
				'surface'       => 'admin_bulk_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.bulk.g5',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '+۵ GB' ),
				'route'         => array( 'bulk_gb' => 5 ),
			),
			'bulk.confirm_text'   => array(
				'surface'       => 'admin_bulk_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.bulk.confirm_text',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📝 تأیید متنی گروهی' ),
				'route'         => array( 'hub_dispatch_cb' => 'pnl:hcb' ),
			),
			'inbound.list'        => array(
				'surface'       => 'admin_inbound_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.inbound.list',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '📋 لیست Inbound' ),
				'route'         => array( 'panel_tab' => 'configs' ),
			),
			'hub.toggle.enabled'  => array(
				'surface'       => 'admin_general_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.hub.toggle_enabled',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '🔛 ربات فعال/غیر' ),
				'route'         => array( 'toggle_setting' => 'enabled' ),
			),
			'hub.toggle.test'     => array(
				'surface'       => 'admin_general_submenu',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.hub.toggle_test',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '🧪 تست فعال/غیر' ),
				'route'         => array( 'toggle_setting' => 'test_account_enabled' ),
			),
		);
	}

	/**
	 * Global shortcuts (flat hub map fallbacks).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function admin_misc_actions() {
		return array(
			'admin.nav.catalog' => array(
				'surface'       => 'admin_misc',
				'kind'          => 'reply',
				'text_key'      => 'btn.admin.nav.catalog',
				'glass_default' => false,
				'max_len'       => 256,
				'legacy'        => array( '⚙️ تنظیمات ربات' ),
				'route'         => array( 'pnl_submenu' => 'set' ),
			),
		);
	}

	/**
	 * Bot text key for an inline service menu slot (must match class-keyboards.php i18n_btn keys).
	 *
	 * @param string $slot Template slot id (e.g. svc_xray.panel).
	 * @return string
	 */
	private static function svc_inline_slot_text_key( $slot ) {
		switch ( (string) $slot ) {
			case 'svc_xray.panel':
				return 'btn.service.show_panel';
			case 'svc_xray.usage':
				return 'btn.svc.show_usage';
			case 'svc_xray.config':
				return 'btn.svc.config_qr';
			case 'svc_xray.regen':
				return 'btn.svc.regenerate_key';
			case 'svc_xray.panel_toggle':
				return 'btn.svc.panel_enable';
			case 'svc_xray.refresh':
				return 'btn.svc.update_servers';
			case 'svc_xray.renew':
				return 'btn.svc.renew_short';
			case 'svc_xray.volume':
				return 'btn.svc.add_volume';
			case 'svc_xray.slots':
				return 'btn.svc.add_users';
			case 'svc_xray.rename':
				return 'btn.svc.rename';
			case 'svc_xray.note':
				return 'btn.svc.panel_note';
			case 'svc_xray.alerts':
				return 'btn.svc.alerts';
			case 'svc_xray.ip':
				return 'btn.svc.active_connections';
			case 'svc_xray.faq':
				return 'btn.svc.faq_short';
			case 'svc_xray.transfer':
				return 'btn.svc.transfer';
			case 'svc_xray.support':
				return 'btn.svc.support';
			case 'svc_xray.back':
				return 'btn.common.back';
			case 'svc_xray.del_admin':
				return 'btn.admin.delete_service_soft';
			case 'svc_l2tp.conn':
				return 'btn.svc.show_connection';
			case 'svc_l2tp.usage':
				return 'btn.svc.show_usage';
			case 'svc_l2tp.portal':
				return 'btn.common.web_panel';
			case 'svc_l2tp.pass':
				return 'btn.svc.change_password';
			case 'svc_l2tp.renew':
				return 'btn.svc.renew';
			case 'svc_l2tp.autorenew':
				return 'btn.svc.auto_renew';
			case 'svc_l2tp.alerts':
				return 'btn.svc.alerts';
			case 'svc_l2tp.rename':
				return 'btn.svc.rename';
			case 'svc_l2tp.faq':
				return 'btn.svc.faq';
			case 'svc_l2tp.support':
				return 'btn.svc.support';
			case 'svc_l2tp.transfer':
				return 'btn.svc.transfer';
			case 'svc_l2tp.del_admin':
				return 'btn.admin.delete_service_soft';
			case 'svc_l2tp.back':
				return 'btn.common.back';
			case 'purchase_qr.tutorial':
				return 'btn.purchase.delivery.tutorial';
			case 'purchase_qr.faq':
				return 'btn.purchase.delivery.faq';
			case 'purchase_qr.support':
				return 'btn.purchase.delivery.support';
			case 'purchase_cfg.copy_all':
				return 'btn.purchase.delivery.copy_all';
			default:
				return '';
		}
	}

	/**
	 * Xray service menu slots (inline_template).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function svc_xray_slots() {
		$slots = array(
			'svc_xray.panel',
			'svc_xray.usage',
			'svc_xray.config',
			'svc_xray.regen',
			'svc_xray.panel_toggle',
			'svc_xray.refresh',
			'svc_xray.renew',
			'svc_xray.volume',
			'svc_xray.slots',
			'svc_xray.rename',
			'svc_xray.note',
			'svc_xray.alerts',
			'svc_xray.ip',
			'svc_xray.faq',
			'svc_xray.transfer',
			'svc_xray.support',
			'svc_xray.back',
			'svc_xray.del_admin',
		);
		$out = array();
		foreach ( $slots as $id ) {
			$tk      = self::svc_inline_slot_text_key( $id );
			$out[ $id ] = array(
				'surface'       => 'svc_menu_xray',
				'kind'          => 'inline_template',
				'template_slot' => $id,
				'text_key'      => $tk,
				'glass_default' => true,
				'max_len'       => 64,
			);
		}
		return $out;
	}

	/**
	 * L2TP service menu slots.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function svc_l2tp_slots() {
		$slots = array(
			'svc_l2tp.conn',
			'svc_l2tp.usage',
			'svc_l2tp.portal',
			'svc_l2tp.pass',
			'svc_l2tp.renew',
			'svc_l2tp.autorenew',
			'svc_l2tp.alerts',
			'svc_l2tp.rename',
			'svc_l2tp.faq',
			'svc_l2tp.support',
			'svc_l2tp.transfer',
			'svc_l2tp.del_admin',
			'svc_l2tp.back',
		);
		$out = array();
		foreach ( $slots as $id ) {
			$tk      = self::svc_inline_slot_text_key( $id );
			$out[ $id ] = array(
				'surface'       => 'svc_menu_l2tp',
				'kind'          => 'inline_template',
				'template_slot' => $id,
				'text_key'      => $tk,
				'glass_default' => true,
				'max_len'       => 64,
			);
		}
		return $out;
	}

	/**
	 * Post-purchase Telegram delivery inline buttons (QR message + configs message).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function purchase_delivery_slots() {
		$qr_slots = array(
			'purchase_qr.tutorial',
			'purchase_qr.faq',
			'purchase_qr.support',
		);
		$out = array();
		foreach ( $qr_slots as $id ) {
			$tk       = self::svc_inline_slot_text_key( $id );
			$out[ $id ] = array(
				'surface'       => 'purchase_delivery_qr',
				'kind'          => 'inline_template',
				'template_slot' => $id,
				'text_key'      => $tk,
				'glass_default' => true,
				'max_len'       => 64,
			);
		}
		$out['purchase_cfg.copy_all'] = array(
			'surface'       => 'purchase_delivery_configs',
			'kind'          => 'inline_template',
			'template_slot' => 'purchase_cfg.copy_all',
			'text_key'      => 'btn.purchase.delivery.copy_all',
			'glass_default' => true,
			'max_len'       => 64,
		);
		return $out;
	}

	/**
	 * Default grid rows per surface (list of action ids).
	 *
	 * @return array<string, array<int, array<int, string>>>
	 */
	public static function default_surface_rows() {
		$rows = array(
			'user_main'                => array(
				array( 'user.main.buy', 'user.main.manage' ),
				array( 'user.main.apps', 'user.main.support' ),
				array( 'user.main.account', 'user.main.wallet' ),
				array( 'user.main.referral' ),
			),
			'admin_main'               => array(
				array( 'admin.root.dashboard', 'admin.root.users' ),
				array( 'admin.root.finance', 'admin.root.settings' ),
				array( 'admin.root.advanced' ),
			),
			'admin_users_submenu'      => array(
				array( 'admin.users.search', 'admin.users.queue' ),
				array( 'admin.users.transfer', 'admin.users.bulk' ),
				array( 'admin.users.broadcast' ),
			),
			'admin_finance_submenu'    => array(
				array( 'admin.finance.receipts' ),
			),
			'admin_settings_catalog'   => array(
				array( 'admin.cat.plan_cats', 'admin.cat.plans', 'admin.cat.cards' ),
				array( 'admin.cat.panel', 'admin.cat.l2tp', 'admin.cat.config' ),
				array( 'admin.cat.crypto', 'admin.cat.bots' ),
			),
			'admin_settings_advanced'  => array(
				array( 'admin.adv.backup' ),
				array( 'admin.adv.general', 'admin.adv.notif' ),
				array( 'admin.adv.texts', 'admin.adv.logs' ),
				array( 'admin.adv.broadcast' ),
			),
			'admin_general_submenu'    => array(
				array( 'hub.toggle.enabled', 'hub.toggle.test' ),
				array( 'wiz.gen.at', 'wiz.gen.ab' ),
				array( 'wiz.gen.pp' ),
				array( 'wiz.gen.dp' ),
			),
			'admin_bot_submenu'        => array(
				array( 'op.bot.getme', 'op.bot.wh_tg', 'op.bot.wh_bl' ),
				array( 'wiz.bot.tt', 'wiz.bot.bt' ),
				array( 'wiz.bot.ts', 'wiz.bot.bs' ),
				array( 'wiz.bot.th', 'wiz.bot.bw' ),
			),
			'admin_panel_submenu'      => array(
				array( 'op.pan.test' ),
				array( 'wiz.pan.u', 'wiz.pan.n', 'wiz.pan.p' ),
				array( 'wiz.pan.a', 'wiz.pan.l' ),
				array( 'wiz.pan.s' ),
			),
			'admin_notif_submenu'      => array(
				array( 'wiz.not.l' ),
				array( 'wiz.not.e' ),
				array( 'wiz.not.d' ),
				array( 'wiz.not.p' ),
			),
			'admin_bulk_submenu'       => array(
				array( 'bulk.days.1', 'bulk.days.7', 'bulk.days.30' ),
				array( 'bulk.gb.1', 'bulk.gb.5' ),
				array( 'bulk.confirm_text' ),
			),
			'admin_inbound_submenu'    => array(
				array( 'inbound.list' ),
			),
			'admin_crypto_submenu'     => array(
				array( 'wiz.cry.ak', 'wiz.cry.in', 'wiz.cry.cu' ),
				array( 'hub.crypto.ipn_path' ),
			),
			'admin_misc'               => array(
				array( 'admin.nav.catalog' ),
			),
			'svc_menu_xray'            => array(
				array( 'svc_xray.panel', 'svc_xray.usage' ),
				array( 'svc_xray.config' ),
				array( 'svc_xray.regen', 'svc_xray.panel_toggle', 'svc_xray.refresh' ),
				array( 'svc_xray.renew', 'svc_xray.volume', 'svc_xray.slots' ),
				array( 'svc_xray.rename', 'svc_xray.note' ),
				array( 'svc_xray.alerts' ),
				array( 'svc_xray.ip', 'svc_xray.faq' ),
				array( 'svc_xray.transfer' ),
				array( 'svc_xray.support', 'svc_xray.back' ),
				array( 'svc_xray.del_admin' ),
			),
			'svc_menu_l2tp'            => array(
				array( 'svc_l2tp.conn', 'svc_l2tp.usage' ),
				array( 'svc_l2tp.portal' ),
				array( 'svc_l2tp.pass', 'svc_l2tp.renew' ),
				array( 'svc_l2tp.autorenew', 'svc_l2tp.alerts' ),
				array( 'svc_l2tp.rename' ),
				array( 'svc_l2tp.faq', 'svc_l2tp.support' ),
				array( 'svc_l2tp.transfer' ),
				array( 'svc_l2tp.del_admin' ),
				array( 'svc_l2tp.back' ),
			),
			'purchase_delivery_qr'     => array(
				array( 'purchase_qr.tutorial', 'purchase_qr.faq', 'purchase_qr.support' ),
			),
			'purchase_delivery_configs' => array(
				array( 'purchase_cfg.copy_all' ),
			),
		);
		if ( true ) {
			foreach ( UiCustomGroupsService::get_all() as $g ) {
				$sid = (string) ( $g['surface_id'] ?? '' );
				if ( '' !== $sid ) {
					$rows[ $sid ] = UiCustomGroupsService::default_rows_for_group( $g );
				}
			}
		}
		return $rows;
	}

	/**
	 * @param string $surface Surface id.
	 * @return array<int, string>
	 */
	public static function surface_action_ids_static( $surface ) {
		$surface = (string) $surface;
		$ids     = array();
		foreach ( self::actions() as $id => $def ) {
			if ( isset( $def['surface'] ) && $def['surface'] === $surface ) {
				$ids[] = (string) $id;
			}
		}
		return $ids;
	}

	/**
	 * @param string $surface Surface id.
	 * @return array<int, string>
	 */
	public static function surface_action_ids( $surface ) {
		if ( true ) {
			return UiCustomGroupsService::surface_action_ids( $surface );
		}
		return self::surface_action_ids_static( $surface );
	}

	/**
	 * @param string $action_id Action id.
	 * @return array<string, mixed>|null
	 */
	public static function get_static( $action_id ) {
		$id  = (string) $action_id;
		$all = self::actions();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * @param string $action_id Action id.
	 * @return array<string, mixed>|null
	 */
	public static function get( $action_id ) {
		$id = (string) $action_id;
		$def = self::get_static( $id );
		if ( $def ) {
			return $def;
		}
		if ( true ) {
			return UiCustomGroupsService::hub_action_def_by_id( $id );
		}
		return null;
	}

	/**
	 * Visible reply label for an action (cell glass OR registry glass_default).
	 *
	 * @param string      $action_id Action id.
	 * @param object|null $user      Bot user row.
	 * @param bool        $cell_glass From layout cell.
	 * @return string
	 */
	public static function reply_button_text( $action_id, $user, $cell_glass = false ) {
		$def = self::get( $action_id );
		if ( ! $def || 'reply' !== ( $def['kind'] ?? '' ) ) {
			return '';
		}
		$key = (string) ( $def['text_key'] ?? '' );
		if ( '' === $key ) {
			return '';
		}
		$base = $user && is_object( $user )
			? SimpleVPBot_Texts::get_for_user( $key, $user )
			: SimpleVPBot_Texts::get( $key, '' );
		$use_glass = $cell_glass || ! empty( $def['glass_default'] );
		$max       = (int) ( $def['max_len'] ?? 256 );
		return $use_glass
			? SimpleVPBot_Keyboards::glass_button_text( $base, $max )
			: $base;
	}

	/**
	 * Whether incoming text equals this action's visible label (and legacy aliases).
	 *
	 * @param string      $text      Trimmed message text.
	 * @param object|null $user      User row.
	 * @param string      $action_id Action id.
	 * @param bool        $cell_glass From layout.
	 * @return bool
	 */
	public static function text_matches_reply_action( $text, $user, $action_id, $cell_glass = false ) {
		$def = self::get( $action_id );
		if ( ! $def || 'reply' !== ( $def['kind'] ?? '' ) ) {
			return false;
		}
		if ( $text === self::reply_button_text( $action_id, $user, $cell_glass ) ) {
			return true;
		}
		if ( ! empty( $def['glass_default'] ) || $cell_glass ) {
			$plain = self::reply_button_text( $action_id, $user, false );
			if ( $plain !== '' && $text === $plain ) {
				return true;
			}
		}
		if ( ! empty( $def['legacy'] ) && is_array( $def['legacy'] ) ) {
			foreach ( $def['legacy'] as $leg ) {
				if ( $text === (string) $leg ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Human-readable Bot UI Studio titles per surface (fa/en).
	 *
	 * @return array<string, array{fa:string,en:string}>
	 */
	public static function surface_studio_labels() {
		return array(
			'user_main'               => array(
				'fa' => 'منوی اصلی کاربر',
				'en' => 'User main menu',
			),
			'admin_main'              => array(
				'fa' => 'منوی اصلی ادمین',
				'en' => 'Admin root menu',
			),
			'admin_users_submenu'     => array(
				'fa' => 'ادمین — کاربران',
				'en' => 'Admin — Users',
			),
			'admin_finance_submenu'   => array(
				'fa' => 'ادمین — مالی',
				'en' => 'Admin — Finance',
			),
			'admin_settings_catalog'  => array(
				'fa' => 'ادمین — فهرست تنظیمات',
				'en' => 'Admin — Settings catalog',
			),
			'admin_settings_advanced' => array(
				'fa' => 'ادمین — تنظیمات پیشرفته',
				'en' => 'Admin — Advanced settings',
			),
			'admin_general_submenu'   => array(
				'fa' => 'ادمین — عمومی / تست',
				'en' => 'Admin — General / test',
			),
			'admin_bot_submenu'       => array(
				'fa' => 'ادمین — ربات',
				'en' => 'Admin — Bot',
			),
			'admin_panel_submenu'     => array(
				'fa' => 'ادمین — پنل ۳x-ui',
				'en' => 'Admin — 3x-ui panel',
			),
			'admin_notif_submenu'     => array(
				'fa' => 'ادمین — نوتیفیکیشن',
				'en' => 'Admin — Notifications',
			),
			'admin_bulk_submenu'      => array(
				'fa' => 'ادمین — حجم/روز گروهی',
				'en' => 'Admin — Bulk GB/days',
			),
			'admin_inbound_submenu'   => array(
				'fa' => 'ادمین — Inbound',
				'en' => 'Admin — Inbound',
			),
			'admin_crypto_submenu'    => array(
				'fa' => 'ادمین — رمزارز',
				'en' => 'Admin — Crypto',
			),
			'admin_misc'              => array(
				'fa' => 'ادمین — سایر',
				'en' => 'Admin — Misc',
			),
			'svc_menu_xray'           => array(
				'fa' => 'اینلاین سرویس (Xray)',
				'en' => 'Service inline (Xray)',
			),
			'svc_menu_l2tp'           => array(
				'fa' => 'اینلاین سرویس (L2TP)',
				'en' => 'Service inline (L2TP)',
			),
			'purchase_delivery_qr'    => array(
				'fa' => 'تحویل خرید — QR و راهنما',
				'en' => 'Purchase delivery — QR & guide',
			),
			'purchase_delivery_configs' => array(
				'fa' => 'تحویل خرید — اشتراک‌های تکی',
				'en' => 'Purchase delivery — single configs',
			),
		);
	}

	/**
	 * Studio labels for dashboard chip / picker (fa/en).
	 *
	 * @param array<string, mixed> $d Action definition.
	 * @param string               $action_id Stable id.
	 * @return array{fa:string,en:string}
	 */
	private static function action_studio_labels( array $d, $action_id ) {
		if ( ! empty( $d['studio_label_fa'] ) || ! empty( $d['studio_label_en'] ) ) {
			return array(
				'fa' => (string) ( $d['studio_label_fa'] ?? '' ),
				'en' => (string) ( $d['studio_label_en'] ?? '' ),
			);
		}
		$tk = (string) ( $d['text_key'] ?? '' );
		if ( '' !== $tk && true ) {
			$pair = self::defaultPairForKey( $tk );
			if ( '' !== $pair['fa'] || '' !== $pair['en'] ) {
				return $pair;
			}
		}
		$aid = (string) $action_id;
		return array(
			'fa' => $aid,
			'en' => $aid,
		);
	}

	/**
	 * Registry metadata for dashboard (JSON-safe).
	 *
	 * @return array<string, mixed>
	 */
	public static function export_for_dashboard() {
		$defaults = self::default_surface_rows();
		$labels   = self::surface_studio_labels();
		$surfaces = array();
		foreach ( $defaults as $surface => $_rows ) {
			$actions = array();
			foreach ( self::surface_action_ids( $surface ) as $aid ) {
				$d = self::get( $aid );
				if ( ! $d ) {
					continue;
				}
				$sl = self::action_studio_labels( $d, $aid );
				$cat = true
					? self::groups()->action_studio_category( $aid )
					: 'other';
				$actions[] = array(
					'id'             => $aid,
					'kind'           => (string) ( $d['kind'] ?? 'reply' ),
					'textKey'        => (string) ( $d['text_key'] ?? '' ),
					'glassDefault'   => ! empty( $d['glass_default'] ),
					'templateSlot'   => (string) ( $d['template_slot'] ?? '' ),
					'labelFa'        => $sl['fa'],
					'labelEn'        => $sl['en'],
					'studioCategory' => $cat,
					'isCustomHub'    => ! empty( $d['is_custom_hub'] ),
				);
			}
			$slab = isset( $labels[ $surface ] ) ? $labels[ $surface ] : array( 'fa' => $surface, 'en' => $surface );
			if ( true && self::groups()->is_custom_surface( $surface ) ) {
				$g = self::groups()->get_by_surface( $surface );
				if ( $g ) {
					$slab = array(
						'fa' => (string) ( $g['label_fa'] ?? $surface ),
						'en' => (string) ( $g['label_en'] ?? $surface ),
					);
				}
			}
			$section = true
				? self::groups()->surface_section( $surface )
				: ( 0 === strpos( (string) $surface, 'user' ) || 0 === strpos( (string) $surface, 'svc_' ) || 0 === strpos( (string) $surface, 'purchase_' ) ? 'user' : 'admin' );
			$surfaces[ $surface ] = array(
				'actions'        => $actions,
				'defaultRows'    => $defaults[ $surface ] ?? array(),
				'labelFa'        => isset( $slab['fa'] ) ? (string) $slab['fa'] : $surface,
				'labelEn'        => isset( $slab['en'] ) ? (string) $slab['en'] : $surface,
				'section'        => $section,
				'supportsGroups' => true
					? self::groups()->surface_supports_groups( $surface )
					: false,
				'isCustomGroup'  => true
					? self::groups()->is_custom_surface( $surface )
					: false,
			);
		}
		$custom_groups = true
			? self::groups()->export_for_dashboard()
			: array();
		return array(
			'version'      => self::LAYOUT_VERSION,
			'surfaces'     => $surfaces,
			'customGroups' => $custom_groups,
		);
	}

    /** @return array{fa:string,en:string} */
    public static function defaultPairForKey(string $key): array
    {
        $defaults = app(SettingsStore::class)->get('text_defaults', []);
        if (is_array($defaults) && isset($defaults[$key]) && is_array($defaults[$key])) {
            $row = $defaults[$key];
            return [
                'fa' => (string) ($row['fa'] ?? ''),
                'en' => (string) ($row['en'] ?? ''),
            ];
        }

        return ['fa' => '', 'en' => ''];
    }

    public static function l2tpEnabled(): bool
    {
        return (bool) config('modules.modules.l2tp.enabled', false);
    }

    protected static function groups(): UiCustomGroupsService
    {
        return app(UiCustomGroupsService::class);
    }
}
