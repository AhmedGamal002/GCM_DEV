<?php

namespace App\Models;

use App\Casts\SanitizedRichText;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FRD V01.14 §1.13 — a purchase order (contract): the quota of main
 * services a client company buys for ONE of its projects over a date range.
 * Its lines are PurchaseOrderService rows; what has been consumed lives in
 * the append-only quota ledger (QuotaLedgerEntry), never on the PO itself.
 *
 * The rules around it live in App\Domain\PurchaseOrders:
 *  - Quota\QuotaBalances / QuotaBalance  — what is left on a line
 *  - Quota\PurchaseOrderStatus           — valid / about to run out / exhausted, not started / ended
 *  - Quota\ServiceExhaustion             — when a line no longer blocks a new PO
 *  - Rules\NoOverlappingMainServiceRule  — one live PO per project + main service + period
 *  - Support\PurchaseOrderEditability    — what can still change once trips are recorded on it
 *
 * $fillable excludes:
 *  - tenant_id                 — stamped by BelongsToTenant
 *  - company_id, project_id,
 *    number                    — set by the create/update actions, which keep
 *                                the number built from the company's prefix and
 *                                the project inside that company
 *  - operational_status        — only ever set through the actions
 *  - attachment_path           — set after the upload is stored
 *  - updated_by                — set explicitly by the actions
 */
class PurchaseOrder extends Model
{
    use BelongsToTenant, HasFactory;

    /** The paper contract's number: digits only, up to 10, leading zeros kept (decision ب4). */
    public const PAPER_NUMBER_PATTERN = '/^[0-9]{1,10}$/';

    protected $fillable = [
        'starts_on',
        'ends_on',
        'region',
        'additional_data',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'additional_data' => SanitizedRichText::class,
        ];
    }

    /** "ALN" + "1200" → "ALN-1200" — the stored, unique `number`. */
    public static function composeNumber(string $companyPrefix, string $paperNumber): string
    {
        return $companyPrefix.'-'.$paperNumber;
    }

    /** The paper contract's number without the company prefix ("1200" for "ALN-1200"). */
    public function paperNumber(): string
    {
        $dash = strpos((string) $this->number, '-');

        return $dash === false ? (string) $this->number : substr($this->number, $dash + 1);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The PO's lines ("بنود التعاقد"), one per main service. */
    public function services(): HasMany
    {
        return $this->hasMany(PurchaseOrderService::class)->orderBy('id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->operational_status === 'active';
    }
}
