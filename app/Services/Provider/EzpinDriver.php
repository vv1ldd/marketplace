<?php

namespace App\Services\Provider;

/**
 * Meanly.one is the provider authority; EZPin is the upstream vendor.
 *
 * The parent driver still carries legacy Wildflow naming because older rows and
 * ledger payloads reference it. New provider selection should enter here.
 */
class EzpinDriver extends WildflowDriver
{
}
