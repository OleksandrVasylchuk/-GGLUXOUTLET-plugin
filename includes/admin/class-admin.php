<?php
/**
 * Admin area.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Admin;

use PromoEngine\Analytics\Reports;
use PromoEngine\Demo\Seeder;
use PromoEngine\Promotion\Labels;
use PromoEngine\Promotion\Promotion;
use PromoEngine\Promotion\Repository;
use PromoEngine\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, list screen, row actions and settings.
 */
final class Admin {

	public const CAPABILITY     = 'manage_woocommerce';
	public const PAGE_LIST      = 'promo-engine';
	public const PAGE_EDIT      = 'promo-engine-edit';
	public const PAGE_ANALYTICS = 'promo-engine-analytics';
	public const PAGE_SETTINGS  = 'promo-engine-settings';

	private Promotion_Form $form;
	private Analytics_Page $analytics;
	private ?Promotions_Table $table = null;

	/**
	 * Constructor.
	 *
	 * @param Repository $promotions Promotions.
	 * @param Reports    $reports    Reports.
	 * @param Seeder     $seeder     Demo seeder.
	 */
	public function __construct(
		private Repository $promotions,
		Reports $reports,
		private Seeder $seeder
	) {
		$this->form      = new Promotion_Form( $promotions );
		$this->analytics = new Analytics_Page( $promotions, $reports );
	}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_action( 'admin_post_promo_engine_status', array( $this, 'handle_status' ) );
		add_action( 'admin_post_promo_engine_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_promo_engine_seed', array( $this, 'handle_seed' ) );
		add_filter( 'set_screen_option_promo_engine_per_page', static fn( $keep, $option, $value ) => absint( $value ), 10, 3 );
	}

	/**
	 * Admin URL of a plugin page.
	 *
	 * @param string               $page Page slug.
	 * @param array<string, mixed> $args Extra query args.
	 */
	public static function url( string $page, array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Register menu pages.
	 */
	public function menu(): void {
		add_menu_page(
			__( 'Promotions', 'promo-engine' ),
			__( 'Promotions', 'promo-engine' ),
			self::CAPABILITY,
			self::PAGE_LIST,
			array( $this, 'render_list' ),
			'dashicons-tag',
			56
		);

		$list = add_submenu_page( self::PAGE_LIST, __( 'Promotions', 'promo-engine' ), __( 'All promotions', 'promo-engine' ), self::CAPABILITY, self::PAGE_LIST, array( $this, 'render_list' ) );
		$edit = add_submenu_page( self::PAGE_LIST, __( 'Edit promotion', 'promo-engine' ), __( 'Add new', 'promo-engine' ), self::CAPABILITY, self::PAGE_EDIT, array( $this->form, 'render' ) );

		add_submenu_page( self::PAGE_LIST, __( 'Promotion analytics', 'promo-engine' ), __( 'Analytics', 'promo-engine' ), self::CAPABILITY, self::PAGE_ANALYTICS, array( $this->analytics, 'render' ) );
		add_submenu_page( self::PAGE_LIST, __( 'Promotion settings', 'promo-engine' ), __( 'Settings', 'promo-engine' ), self::CAPABILITY, self::PAGE_SETTINGS, array( $this, 'render_settings' ) );

		add_action( 'load-' . $list, array( $this, 'load_list' ) );
		add_action( 'load-' . $edit, array( $this->form, 'handle' ) );
	}

	/**
	 * Enqueue assets on plugin screens.
	 *
	 * @param string $hook_suffix Current screen hook.
	 */
	public function enqueue( $hook_suffix ): void {
		if ( ! str_contains( (string) $hook_suffix, self::PAGE_LIST ) ) {
			return;
		}

		wp_enqueue_style( 'promo-engine-admin', PROMO_ENGINE_URL . 'assets/css/admin.css', array(), PROMO_ENGINE_VERSION );
		wp_enqueue_script( 'promo-engine-admin', PROMO_ENGINE_URL . 'assets/js/admin.js', array(), PROMO_ENGINE_VERSION, true );

		if ( str_ends_with( $hook_suffix, self::PAGE_EDIT ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_script( 'wc-enhanced-select' );
		}

		if ( str_ends_with( $hook_suffix, self::PAGE_ANALYTICS ) ) {
			$this->analytics->enqueue();
		}
	}

	/**
	 * Build the list table and process bulk actions before output.
	 */
	public function load_list(): void {
		add_screen_option(
			'per_page',
			array(
				'default' => 20,
				'option'  => 'promo_engine_per_page',
			)
		);

		$this->table = new Promotions_Table( $this->promotions );

		$action = $this->table->current_action();

		if ( ! $action ) {
			return;
		}

		check_admin_referer( 'bulk-promotions' );

		$ids = array_map( 'absint', (array) wp_unslash( $_REQUEST['promotion'] ?? array() ) );

		if ( ! $ids ) {
			return;
		}

		match ( $action ) {
			'activate' => $this->promotions->set_status( $ids, Promotion::STATUS_ACTIVE ),
			'pause'    => $this->promotions->set_status( $ids, Promotion::STATUS_PAUSED ),
			'delete'   => $this->promotions->delete( $ids ),
			default    => null,
		};

		$this->redirect( self::url( self::PAGE_LIST, array( 'pe_notice' => 'delete' === $action ? 'deleted' : 'updated' ) ) );
	}

	/**
	 * Promotions list screen.
	 */
	public function render_list(): void {
		$table = $this->table ?? new Promotions_Table( $this->promotions );
		$table->prepare_items();

		include PROMO_ENGINE_DIR . 'views/admin/promotions-list.php';
	}

	/**
	 * Pause / activate from a row action.
	 */
	public function handle_status(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the nonce is checked in authorize().
		$id = absint( $_GET['id'] ?? 0 );

		$this->authorize( 'promo_engine_status_' . $id );

		$status = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
		// phpcs:enable

		if ( isset( Labels::statuses()[ $status ] ) ) {
			$this->promotions->set_status( array( $id ), $status );
		}

		$this->redirect( self::url( self::PAGE_LIST, array( 'pe_notice' => 'updated' ) ) );
	}

	/**
	 * Delete from a row action.
	 */
	public function handle_delete(): void {
		$id = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked in authorize().

		$this->authorize( 'promo_engine_delete_' . $id );
		$this->promotions->delete( array( $id ) );

		$this->redirect( self::url( self::PAGE_LIST, array( 'pe_notice' => 'deleted' ) ) );
	}

	/**
	 * Load the demo promotions.
	 */
	public function handle_seed(): void {
		$this->authorize( 'promo_engine_seed' );

		$categories = array_map( 'absint', (array) wp_unslash( $_POST['categories'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is checked in authorize().

		if ( count( array_filter( $categories ) ) < 3 ) {
			$this->redirect( self::url( self::PAGE_LIST, array( 'pe_notice' => 'seed_failed' ) ) );
		}

		$this->seeder->seed( $categories[0], $categories[1], $categories[2] );

		$this->redirect( self::url( self::PAGE_LIST, array( 'pe_notice' => 'seeded' ) ) );
	}

	/**
	 * Register the settings option and fields.
	 */
	public function register_settings(): void {
		register_setting(
			'promo_engine',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		add_filter( 'option_page_capability_promo_engine', static fn() => self::CAPABILITY );
	}

	/**
	 * Settings screen.
	 */
	public function render_settings(): void {
		$settings = Settings::all();

		include PROMO_ENGINE_DIR . 'views/admin/settings.php';
	}

	/**
	 * Result notices after redirects.
	 */
	public function notices(): void {
		$page   = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = sanitize_key( wp_unslash( $_GET['pe_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $notice || ! str_starts_with( $page, self::PAGE_LIST ) ) {
			return;
		}

		$messages = array(
			'saved'       => array( 'success', __( 'Promotion saved.', 'promo-engine' ) ),
			'updated'     => array( 'success', __( 'Promotions updated.', 'promo-engine' ) ),
			'deleted'     => array( 'success', __( 'Promotions deleted.', 'promo-engine' ) ),
			'seeded'      => array( 'success', __( 'Demo promotions loaded.', 'promo-engine' ) ),
			'seed_failed' => array( 'error', __( 'Choose three categories to load the demo promotions.', 'promo-engine' ) ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			wp_admin_notice(
				$messages[ $notice ][1],
				array(
					'type'        => $messages[ $notice ][0],
					'dismissible' => true,
				)
			);
		}
	}

	/**
	 * Verify capability and nonce for admin-post handlers.
	 *
	 * @param string $action Nonce action.
	 */
	private function authorize( string $action ): void {
		self::check_capability();
		check_admin_referer( $action );
	}

	/**
	 * Stop the request unless the user may manage promotions.
	 */
	public static function check_capability(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage promotions.', 'promo-engine' ), 403 );
		}
	}

	/**
	 * Redirect and stop.
	 *
	 * @param string $url Target URL.
	 */
	private function redirect( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}
