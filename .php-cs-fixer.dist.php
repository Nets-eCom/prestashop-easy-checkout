<?php
$psConfig = new PrestaShop\CodingStandards\CsFixer\Config();
$rules = $psConfig->getRules();

$rules['blank_line_after_opening_tag'] = false;

if (isset($rules['header_comment'])) {
    $rules['header_comment']['separate'] = 'none';
}

$config = new PhpCsFixer\Config();

return $config
    ->setRules($rules)
    ->setUsingCache(false)
    ->setRiskyAllowed(true)
    ->setFinder(
        $psConfig->getFinder()
            ->in(__DIR__)
            ->exclude('vendor/')
            ->exclude('frontend/node_modules')
    );
