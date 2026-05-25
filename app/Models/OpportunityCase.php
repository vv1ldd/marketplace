<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class OpportunityCase extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_ARCHIVED = 'archived';

    public const ACTION_ADD_SUPPLY = 'add_supply';
    public const ACTION_IMPROVE_PRICING = 'improve_pricing';
    public const ACTION_FIX_CHECKOUT = 'fix_checkout';
    public const ACTION_INVESTIGATE = 'investigate';

    public const TEAM_CONTENT = 'content_team';
    public const TEAM_COMMERCIAL = 'commercial_team';
    public const TEAM_PAYMENTS = 'payments_team';
    public const TEAM_SUPPLIERS = 'supplier_team';
    public const TEAM_OPERATIONS = 'operations_team';

    protected $fillable = [
        'canonical_query',
        'status',
        'owner_team',
        'sla_due_at',
        'auto_created',
        'auto_reason',
        'before_opportunity_score',
        'before_search_volume',
        'before_views_count',
        'before_carts_count',
        'before_orders_count',
        'before_gmv',
        'before_diagnosis',
        'before_diagnosis_graph',
        'action_type',
        'action_details',
        'action_taken_at',
        'after_opportunity_score',
        'after_search_volume',
        'after_views_count',
        'after_carts_count',
        'after_orders_count',
        'after_gmv',
        'after_diagnosis',
        'after_diagnosis_graph',
        'gmv_growth_percentage',
        'conversion_growth_percentage',
        'resolved_at',
    ];

    protected $casts = [
        'sla_due_at' => 'datetime',
        'auto_created' => 'boolean',
        'before_opportunity_score' => 'float',
        'before_search_volume' => 'integer',
        'before_views_count' => 'integer',
        'before_carts_count' => 'integer',
        'before_orders_count' => 'float',
        'before_gmv' => 'float',
        'before_diagnosis_graph' => 'array',
        'action_taken_at' => 'datetime',
        'after_opportunity_score' => 'float',
        'after_search_volume' => 'integer',
        'after_views_count' => 'integer',
        'after_carts_count' => 'integer',
        'after_orders_count' => 'float',
        'after_gmv' => 'float',
        'after_diagnosis_graph' => 'array',
        'gmv_growth_percentage' => 'float',
        'conversion_growth_percentage' => 'float',
        'resolved_at' => 'datetime',
    ];

    public function demandGap(): HasOne
    {
        return $this->hasOne(DemandGap::class, 'canonical_query', 'canonical_query');
    }

    /**
     * Record operator action and transition to in_progress.
     */
    public function recordAction(string $type, string $details): void
    {
        $this->update([
            'status' => 'in_progress',
            'action_type' => $type,
            'action_details' => $details,
            'action_taken_at' => now(),
        ]);
    }

    /**
     * Resolve the opportunity case and compute outcomes.
     */
    public function resolve(array $current): void
    {
        $afterScore = (float) ($current['opportunity_score'] ?? 0.0);
        $afterVolume = (int) ($current['search_volume'] ?? 0);
        $afterViews = (int) ($current['views_count'] ?? 0);
        $afterCarts = (int) ($current['carts_count'] ?? 0);
        $afterOrders = (float) ($current['attributed_orders_count'] ?? 0.0);
        $afterGmv = (float) ($current['attributed_gmv'] ?? 0.0);
        $afterDiag = (string) ($current['opportunity_diagnosis'] ?? 'unknown');
        $afterGraph = (array) ($current['opportunity_diagnosis_graph'] ?? []);

        // Compute conversion percentages
        $beforeConv = $this->before_search_volume > 0 ? ($this->before_orders_count / $this->before_search_volume) * 100 : 0.0;
        $afterConv = $afterVolume > 0 ? ($afterOrders / $afterVolume) * 100 : 0.0;

        // Compute growth rates
        $gmvGrowth = $this->before_gmv > 0
            ? (($afterGmv - $this->before_gmv) / $this->before_gmv) * 100
            : ($afterGmv > 0 ? 100.0 : 0.0);

        $convGrowth = $beforeConv > 0
            ? (($afterConv - $beforeConv) / $beforeConv) * 100
            : ($afterConv > 0 ? 100.0 : 0.0);

        $this->update([
            'status' => 'resolved',
            'after_opportunity_score' => $afterScore,
            'after_search_volume' => $afterVolume,
            'after_views_count' => $afterViews,
            'after_carts_count' => $afterCarts,
            'after_orders_count' => $afterOrders,
            'after_gmv' => $afterGmv,
            'after_diagnosis' => $afterDiag,
            'after_diagnosis_graph' => $afterGraph,
            'gmv_growth_percentage' => round($gmvGrowth, 1),
            'conversion_growth_percentage' => round($convGrowth, 1),
            'resolved_at' => now(),
        ]);
    }
}
