<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Support;

use GraystackIT\Ahasend\Contracts\InboundScreen;
use GraystackIT\Ahasend\Data\InboundDelivery;
use GraystackIT\Ahasend\Data\ScreenVerdict;

/**
 * The default screen: every verified delivery goes to the listeners.
 */
final class AcceptAllScreen implements InboundScreen
{
    /**
     * Accept everything.
     *
     * @param  InboundDelivery  $delivery  The verified delivery.
     */
    public function screen(InboundDelivery $delivery): ScreenVerdict
    {
        return ScreenVerdict::accept();
    }

    /**
     * Never reached, since nothing is deferred.
     *
     * @param  InboundDelivery  $delivery  The delivery.
     */
    public function gaveUp(InboundDelivery $delivery): void {}
}
