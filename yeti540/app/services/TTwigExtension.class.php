<?php

namespace App\Services;

use App\Helpers\TranslateHelper;

/**
 * Class to provide translation support to Twig templates
 *
 * @author Adam
 */
class TTwigExtension extends \Twig_Extension
{

    public function getFunctions() {
        return array(
            new \Twig_Function('t', array($this, 'tHandler')),
        );
    }

    public function tHandler($transKey, $transDefault = null, $replacements = array()) {
        return TranslateHelper::t($transKey, $transDefault, $replacements);
    }

    public function getName() {
        return 't_twig_extension';
    }

}
