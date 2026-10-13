<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Contracts;

use GraystackIT\Ahasend\Data\InboundDelivery;
use GraystackIT\Ahasend\Data\ScreenVerdict;

/**
 * Looks at every verified delivery before any listener does.
 *
 * The one place an application can refuse a delivery for every consumer at
 * once — a virus scan is the case it exists for. Bind an implementation to
 * this interface in the container; the package's own default accepts all.
 */
interface InboundScreen
{
    /**
     * Decide whether the listeners get this delivery.
     *
     * @param  InboundDelivery  $delivery  The verified delivery.
     */
    public function screen(InboundDelivery $delivery): ScreenVerdict;

    /**
     * A delivery that was deferred until the retry window closed.
     *
     * Called once, after which the payload is deleted. The place to tell the
     * sender, where that is wanted.
     *
     * @param  InboundDelivery  $delivery  The delivery that was never accepted.
     */
    public function gaveUp(InboundDelivery $delivery): void;
}
