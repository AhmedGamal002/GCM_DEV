<?php

namespace App\Domain\Vehicles\Exceptions;

use RuntimeException;

/**
 * A category still referenced by vehicles, drivers' qualified categories,
 * assets' compatible categories or main services' supported categories
 * can't be deleted. Vehicles alone are protected by a restrict FK, but the
 * pivot tables cascade — without this check a delete would silently strip
 * driver qualifications, asset compatibility and service support instead
 * of failing.
 */
class VehicleCategoryInUseException extends RuntimeException
{
    public function __construct(
        public readonly int $vehicles,
        public readonly int $drivers,
        public readonly int $assets,
        public readonly int $mainServices = 0,
    ) {
        $parts = array_filter([
            $vehicles ? trans_choice('{1} :count vehicle|[2,*] :count vehicles', $vehicles, ['count' => $vehicles]) : null,
            $drivers ? trans_choice('{1} :count driver|[2,*] :count drivers', $drivers, ['count' => $drivers]) : null,
            $assets ? trans_choice('{1} :count asset|[2,*] :count assets', $assets, ['count' => $assets]) : null,
            $mainServices ? trans_choice('{1} :count main service|[2,*] :count main services', $mainServices, ['count' => $mainServices]) : null,
        ]);

        parent::__construct(__('This category can\'t be deleted because it is in use by :usage.', ['usage' => implode(__(', '), $parts)]));
    }
}
