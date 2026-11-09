<?php

declare(strict_types=1);

namespace Funnel;

use Funnel\Security\SignedToken;
use Funnel\Support\Config;
use Funnel\Support\I18n;
use Funnel\Support\View;

/**
 * Everything tracking-related that ends up in the HTML.
 *
 * Principle: the page only loads GTM and pushes well-defined dataLayer events.
 * GA4, Meta, LinkedIn, TikTok tags and the cookie consent are configured in GTM
 * by the marketing partner. See docs/tracking-plan.md for the event contract.
 */
final class Tracking
{
    public static function gtmId(): ?string
    {
        $id = (string) Config::get('tracking.gtm_id', '');
        return preg_match('/^GTM-[A-Z0-9]{4,}$/', $id) && $id !== 'GTM-XXXXXXX' ? $id : null;
    }

    /** Initial dataLayer state + GTM loader, goes first in <head>. */
    public static function head(string $page): string
    {
        $state = [
            'funnel_name' => Config::get('funnel.slug'),
            'funnel_version' => Config::get('funnel.version'),
            'funnel_step' => Config::get("pages.{$page}.funnel_step"),
            'page_type' => $page,
            'language' => I18n::language(),
        ];

        $html = "<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push("
            . self::json($state) . ");</script>\n";

        $lead = self::leadEvent();
        if ($lead !== null) {
            $html .= "<script>window.dataLayer.push(" . self::json($lead) . ");</script>\n";
        }

        $gtm = self::gtmId();
        if ($gtm !== null && !View::$preview) {
            $html .= "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':"
                . "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],"
                . "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src="
                . "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);"
                . "})(window,document,'script','dataLayer','" . $gtm . "');</script>\n<!-- End Google Tag Manager -->\n";
        }
        return $html;
    }

    /** GTM noscript fallback, goes right after <body>. */
    public static function body(): string
    {
        $gtm = self::gtmId();
        if ($gtm === null || View::$preview) {
            return '';
        }
        return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtm
            . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
    }

    /**
     * The thank-you page receives ?lead=<signed token> after a successful submit.
     * We turn it into a single generate_lead event; funnel.js strips the parameter
     * afterwards so a refresh does not count the lead twice.
     */
    public static function leadEvent(): ?array
    {
        $token = $_GET['lead'] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }
        $payload = SignedToken::verify($token, 'lead', 3600);
        if ($payload === null) {
            return null;
        }
        return [
            'event' => 'generate_lead',
            'form_id' => $payload['form'] ?? null,
            'event_id' => $payload['id'] ?? null, // use as Meta event_id for Pixel/CAPI deduplication
            'language' => I18n::language(),
        ];
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
