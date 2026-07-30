<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pluginRoot = $root . '/wp-content/plugins/waypoints-trip-planner';
$failures = 0;

defined('ABSPATH') || define('ABSPATH', $root . '/');
defined('PLAN_YOUR_DAY_PLUGIN_DIR') || define('PLAN_YOUR_DAY_PLUGIN_DIR', $pluginRoot . '/');
defined('PLAN_YOUR_DAY_PLUGIN_URL') || define('PLAN_YOUR_DAY_PLUGIN_URL', 'https://example.test/wp-content/plugins/waypoints-trip-planner/');
defined('PLAN_YOUR_DAY_VERSION') || define('PLAN_YOUR_DAY_VERSION', '1.0.2');
defined('PLAN_YOUR_DAY_TEXT_DOMAIN') || define('PLAN_YOUR_DAY_TEXT_DOMAIN', 'waypoints-trip-planner');
defined('WEEK_IN_SECONDS') || define('WEEK_IN_SECONDS', 604800);

$GLOBALS['waypoints_test_options'] = [];
$GLOBALS['waypoints_test_shortcodes'] = [];
$GLOBALS['waypoints_test_enqueued_styles'] = [];
$GLOBALS['waypoints_test_enqueued_scripts'] = [];
$GLOBALS['waypoints_unique_id'] = 0;

function __($text, $domain = null): string
{
    return (string) $text;
}

function _n($single, $plural, $number, $domain = null): string
{
    return (int) $number === 1 ? (string) $single : (string) $plural;
}

function esc_html__($text, $domain = null): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_attr__($text, $domain = null): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_html_e($text, $domain = null): void
{
    echo esc_html__($text, $domain);
}

function sanitize_text_field($value): string
{
    return trim(strip_tags((string) $value));
}

function sanitize_textarea_field($value): string
{
    return trim(strip_tags((string) $value));
}

function sanitize_key($value): string
{
    return strtolower((string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value));
}

function sanitize_title($value): string
{
    $value = strtolower(trim((string) $value));
    $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

    return trim($value, '-');
}

function wp_unslash($value)
{
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }

    return is_string($value) ? stripslashes($value) : $value;
}

function absint($value): int
{
    return abs((int) $value);
}

function number_format_i18n($number, $decimals = 0): string
{
    return number_format((float) $number, (int) $decimals);
}

function get_option($name, $default = false)
{
    return $GLOBALS['waypoints_test_options'][$name] ?? $default;
}

function update_option($name, $value): bool
{
    $GLOBALS['waypoints_test_options'][$name] = $value;

    return true;
}

function add_action(): void
{
}

function add_filter(): void
{
}

function register_setting(): void
{
}

function current_user_can($capability): bool
{
    return false;
}

function wp_json_encode($value)
{
    return json_encode($value);
}

function esc_html($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_attr($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_url($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_url_raw($value): string
{
    return trim((string) $value);
}

function checked($checked, $current = true, $echo = true): string
{
    $result = $checked === $current ? ' checked="checked"' : '';

    if ($echo) {
        echo $result;
    }

    return $result;
}

function disabled($disabled, $current = true, $echo = true): string
{
    $result = $disabled === $current ? ' disabled="disabled"' : '';

    if ($echo) {
        echo $result;
    }

    return $result;
}

function wp_unique_id($prefix = ''): string
{
    $GLOBALS['waypoints_unique_id']++;

    return (string) $prefix . $GLOBALS['waypoints_unique_id'];
}

function rest_url($path = ''): string
{
    return 'https://example.test/wp-json/' . ltrim((string) $path, '/');
}

function get_queried_object_id(): int
{
    return 0;
}

function get_permalink($post = 0)
{
    return false;
}

function home_url($path = ''): string
{
    return 'https://example.test/' . ltrim((string) $path, '/');
}

function admin_url($path = ''): string
{
    return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
}

function add_query_arg(array $params, string $url = ''): string
{
    $parts = parse_url($url);
    $query = [];

    if (isset($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }

    foreach ($params as $key => $value) {
        $query[(string) $key] = (string) $value;
    }

    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host = $parts['host'] ?? '';
    $path = $parts['path'] ?? '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

    return $scheme . $host . $path . '?' . http_build_query($query) . $fragment;
}

function add_shortcode(string $tag, callable $callback): void
{
    $GLOBALS['waypoints_test_shortcodes'][$tag] = $callback;
}

function shortcode_atts(array $pairs, array $atts, string $shortcode = ''): array
{
    return array_merge($pairs, array_intersect_key($atts, $pairs));
}

function wp_register_style(): void
{
}

function wp_register_script(): void
{
}

function wp_enqueue_style(string $handle): void
{
    $GLOBALS['waypoints_test_enqueued_styles'][] = $handle;
}

function wp_enqueue_script(
    string $handle,
    string $src = '',
    array $dependencies = [],
    string|bool|null $version = false,
    array|bool $arguments = []
): void {
    unset($src, $dependencies, $version, $arguments);
    $GLOBALS['waypoints_test_enqueued_scripts'][] = $handle;
}

function waypoints_check(string $name, callable $test): void
{
    global $failures;

    try {
        $test();
        echo "PASS: {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        echo "FAIL: {$name}\n";
        echo "  {$error->getMessage()}\n";
    }
}

function waypoints_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function waypoints_assert_contains(string $needle, string $haystack, string $message): void
{
    waypoints_assert(str_contains($haystack, $needle), $message);
}

require_once $root . '/vendor/autoload.php';

use Acodebeard\PlanYourDay\Frontend\FrontendAssets;
use Acodebeard\PlanYourDay\Frontend\PlannerRenderer;
use Acodebeard\PlanYourDay\Frontend\PlannerShortcode;
use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
use Acodebeard\PlanYourDay\Google\GoogleApiResult;
use Acodebeard\PlanYourDay\Planner\CategoryCatalog;
use Acodebeard\PlanYourDay\Planner\DistanceFormatter;
use Acodebeard\PlanYourDay\Planner\MapUrlBuilder;
use Acodebeard\PlanYourDay\Planner\PlannerPayloadBuilder;
use Acodebeard\PlanYourDay\Planner\PlannerStateBuilder;
use Acodebeard\PlanYourDay\Planner\RequestStateParser;
use Acodebeard\PlanYourDay\Planner\StartContextResolver;
use Acodebeard\PlanYourDay\Planner\WaypointList;
use Acodebeard\PlanYourDay\Security\RequestOriginValidator;
use Acodebeard\PlanYourDay\Settings\Settings;

final class RecordingWaypointsGoogleApiClient implements GoogleApiClientInterface
{
    public int $textSearchCalls = 0;
    public int $placeDetailsCalls = 0;
    public int $geocodeCalls = 0;
    public string $lastTextSearchQuery = '';

    public function text_search(string $query, ?float $origin_latitude = null, ?float $origin_longitude = null, string $page_token = ''): GoogleApiResult
    {
        $this->textSearchCalls++;
        $this->lastTextSearchQuery = $query;

        return GoogleApiResult::success([
            'places' => [
                [
                    'id' => 'google-coffee-001',
                    'label' => 'Kona Coffee Stop',
                    'address' => '75-123 Alii Drive, Kailua-Kona, HI',
                    'latitude' => 19.6401,
                    'longitude' => -155.9964,
                    'maps_uri' => 'https://www.google.com/maps/place/?q=place_id:google-coffee-001',
                ],
            ],
            'nextPageToken' => '',
        ]);
    }

    public function place_details(string $place_id, ?int $timeout = null): GoogleApiResult
    {
        $this->placeDetailsCalls++;

        return GoogleApiResult::success([
            'place' => [
                'id' => $place_id,
                'label' => 'Selected Waypoint',
                'address' => '75-456 Alii Drive, Kailua-Kona, HI',
                'latitude' => 19.6410,
                'longitude' => -155.9970,
                'maps_uri' => 'https://www.google.com/maps/place/?q=place_id:' . $place_id,
            ],
        ]);
    }

    public function geocode(string $address): GoogleApiResult
    {
        $this->geocodeCalls++;

        return GoogleApiResult::success([
            'latitude' => 19.6391,
            'longitude' => -155.9969,
        ]);
    }
}

function waypoints_settings(array $overrides = []): Settings
{
    $settings = new Settings();

    update_option(
        Settings::OPTION_NAME,
        array_merge(
            Settings::defaults(),
            [
                'default_location_label' => 'Kailua-Kona Pier',
                'default_location_address' => 'Kailua-Kona Pier, Kailua-Kona, HI',
                'default_location_latitude' => '19.6391',
                'default_location_longitude' => '-155.9969',
                'default_location_place_id' => 'google-default-place',
                'categories' => Settings::default_categories(),
                'google_maps_embed_api_key' => 'local-embed-key',
                'google_places_api_key' => 'local-places-key',
                'google_geocoding_api_key' => 'local-geocoding-key',
            ],
            $overrides
        )
    );

    return $settings;
}

function waypoints_builder(Settings $settings, RecordingWaypointsGoogleApiClient $googleClient): PlannerStateBuilder
{
    $categoryCatalog = new CategoryCatalog($settings);
    $waypointList = new WaypointList($settings);

    return new PlannerStateBuilder(
        $settings,
        $categoryCatalog,
        $googleClient,
        $waypointList,
        new StartContextResolver($settings),
        new MapUrlBuilder(),
        new DistanceFormatter($settings),
        new RequestOriginValidator()
    );
}

function waypoints_renderer(Settings $settings, RecordingWaypointsGoogleApiClient $googleClient): PlannerRenderer
{
    $categoryCatalog = new CategoryCatalog($settings);
    $waypointList = new WaypointList($settings);

    return new PlannerRenderer(
        $settings,
        $categoryCatalog,
        new RequestStateParser($waypointList),
        waypoints_builder($settings, $googleClient),
        new PlannerPayloadBuilder($settings)
    );
}

waypoints_check('installed Waypoints plugin is the current trip-planner package', function () use ($root, $pluginRoot): void {
    $mainPluginFile = $pluginRoot . '/plan-your-day.php';
    $composerFile = $pluginRoot . '/composer.json';
    $oldPluginRoot = $root . '/wp-content/plugins/waypoints';

    waypoints_assert(is_file($mainPluginFile), 'Missing installed Waypoints: Trip Planner plugin file.');
    waypoints_assert(is_file($composerFile), 'Missing installed Waypoints composer metadata.');
    waypoints_assert(! is_dir($oldPluginRoot), 'Old wp-content/plugins/waypoints directory should not be present.');

    $pluginSource = (string) file_get_contents($mainPluginFile);
    $composerSource = (string) file_get_contents($composerFile);

    waypoints_assert_contains('Plugin Name: Waypoints: Trip Planner', $pluginSource, 'Installed plugin should be Waypoints: Trip Planner.');
    waypoints_assert_contains('Version: 1.0.2', $pluginSource, 'Installed plugin should be version 1.0.2.');
    waypoints_assert_contains('waypoints-trip-planner', $composerSource, 'Composer package should use the installed trip-planner slug.');
});

waypoints_check('current shortcode registration uses the installed plugin assets', function (): void {
    $settings = waypoints_settings();
    $googleClient = new RecordingWaypointsGoogleApiClient();
    $shortcode = new PlannerShortcode(
        waypoints_renderer($settings, $googleClient),
        new FrontendAssets()
    );

    $shortcode->register();

    waypoints_assert(PlannerShortcode::TAG === 'waypoints', 'Primary shortcode tag should remain [waypoints].');
    waypoints_assert(array_key_exists('waypoints', $GLOBALS['waypoints_test_shortcodes']), 'Primary shortcode should be registered.');
    waypoints_assert(array_key_exists('plan_your_day', $GLOBALS['waypoints_test_shortcodes']), 'Legacy shortcode alias should be registered.');

    $html = $shortcode->render(['mode' => 'demo'], null, 'waypoints');

    waypoints_assert_contains('class="plan-your-day"', $html, 'Shortcode should render the current planner UI.');
    waypoints_assert_contains('data-plan-root', $html, 'Shortcode should render the current JavaScript mount point.');
    waypoints_assert(! str_contains($html, 'Demo Mode'), 'Installed plugin should not render removed v0.5 demo mode UI.');
    waypoints_assert(in_array(FrontendAssets::STYLE_HANDLE, $GLOBALS['waypoints_test_enqueued_styles'], true), 'Shortcode should enqueue the current style handle.');
    waypoints_assert(in_array(FrontendAssets::SCRIPT_HANDLE, $GLOBALS['waypoints_test_enqueued_scripts'], true), 'Shortcode should enqueue the current script handle.');
});

waypoints_check('current planner state uses Google-backed search results', function (): void {
    $settings = waypoints_settings();
    $googleClient = new RecordingWaypointsGoogleApiClient();
    $builder = waypoints_builder($settings, $googleClient);
    $state = $builder->build(
        [
            'category_key' => 'coffee',
            'start_mode' => Settings::START_MODE_DEFAULT,
        ],
        [
            'include_results' => true,
            'include_trip_waypoints' => true,
        ]
    );

    waypoints_assert($googleClient->textSearchCalls === 1, 'Current category browsing should call Google text search once.');
    waypoints_assert($googleClient->geocodeCalls === 0, 'Configured default coordinates should avoid geocoding the default start.');
    waypoints_assert($googleClient->placeDetailsCalls === 0, 'Browsing without selected waypoints should not call place details.');
    waypoints_assert($googleClient->lastTextSearchQuery === 'Coffee near me', 'Default coffee category should use the installed Google search query.');
    waypoints_assert((string) $state['category_key'] === 'coffee', 'Planner state should keep the selected category.');
    waypoints_assert(count((array) $state['search_results']) === 1, 'Google-backed search should return the provided result.');
    waypoints_assert(str_contains((string) $state['iframe_src'], 'google.com/maps/embed/v1/search'), 'Current planner should build a Google search preview URL.');
    waypoints_assert(str_contains((string) $state['maps_url'], 'google.com/maps/search'), 'Current planner should build a Google handoff URL.');
    waypoints_assert(str_contains((string) $state['search_results_label'], 'Google result'), 'Current labels should identify Google results.');
});

waypoints_check('current planner resolves selected waypoint routes', function (): void {
    $settings = waypoints_settings();
    $googleClient = new RecordingWaypointsGoogleApiClient();
    $builder = waypoints_builder($settings, $googleClient);
    $state = $builder->build(
        [
            'selected_waypoint_ids' => ['google-place-001'],
            'start_mode' => Settings::START_MODE_DEFAULT,
        ],
        [
            'include_results' => false,
            'include_trip_waypoints' => true,
        ]
    );

    waypoints_assert($googleClient->textSearchCalls === 0, 'Route-only state should not call Google text search.');
    waypoints_assert($googleClient->placeDetailsCalls === 1, 'Selected waypoints should resolve through Google place details.');
    waypoints_assert(count((array) $state['trip_waypoints']) === 1, 'Selected waypoint should be present in route state.');
    waypoints_assert((string) $state['iframe_src'] !== '', 'Resolved route should build a Google directions preview URL.');
    waypoints_assert(str_contains((string) $state['maps_url'], 'google.com/maps/dir'), 'Resolved route should build a Google directions handoff URL.');
});

if ($failures > 0) {
    echo "\n{$failures} Waypoints plugin test(s) failed.\n";
    exit(1);
}

echo "\nAll Waypoints plugin tests passed.\n";
