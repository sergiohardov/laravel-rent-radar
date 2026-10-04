<?php

namespace App\Pipeline;

/**
 * Finds a euro two-room flat (a kitchen-living room plus one bedroom) in a
 * listing's text. Sites often list it as one room, so the hard filter asks
 * this before rejecting a one-room listing; cheap enough to spare the LLM.
 */
final class EuroTwoRoomDetector
{
    /**
     * "Euro" in Russian or Ukrainian, not part of "евроремонт", "евроокна" and the like.
     */
    private const EURO = '[еєe]вро(?![\s\-–—]*(ремонт|окн|вікн|двер|пе|стандарт|бетон|вагонк|ламінат|ламинат|сантехн|техн|мебл|меб))';

    private const PATTERNS = [
        // евродвушка, євродвошка, евро-двушка, евро двухкомнатная, євро двокімнатна, евро 2-к, евро 2х
        '/'.self::EURO.'[\s\-–—]*(двуш|двош|двух|двок|дво-|2[\s\-–—]*(х|к|кк|ком|кім)(?![а-яіїєґ\w]*\s*(этаж|поверх)))/iu',
        // 2-к евро, двушка-евро, двухкомнатная (евро)
        '/(\b2[\s\-–—]*(х|к|кк)\b|двуш\w*|двош\w*|двухкомнатн\w*|двокімнатн\w*)[\s\-–—(«"\']*'.self::EURO.'/iu',
    ];

    /** A kitchen-living room or kitchen-studio... */
    private const KITCHEN_LIVING = '/кухн[яиюіе]?[\s\-–—]*(гостин|вітальн|студи|студі|студио)/iu';

    /**
     * ...with a separate bedroom is the same layout, whatever the ad calls it.
     * The noun only: "двуспальная кровать" and "спальное/спальне місце" are a bed.
     */
    private const BEDROOM = '/(?<![а-яіїєґ])спальн(я|и|ю|ей|ею|і|ям|ях|е(?![\s\-–—]*місц))(?![а-яіїєґ])/iu';

    public function matches(?string ...$texts): bool
    {
        $text = implode("\n", array_filter($texts));

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return preg_match(self::KITCHEN_LIVING, $text) === 1 && preg_match(self::BEDROOM, $text) === 1;
    }
}
