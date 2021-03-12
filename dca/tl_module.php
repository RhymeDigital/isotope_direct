<?php

/**
 * Copyright (C) 2021 Rhyme Digital, LLC
 *
 * @author		Blair Winans <blair@rhyme.digital>
 * @author		Adam Fisher <adam@rhyme.digital>
 * @link		https://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace {

    use Contao\CoreBundle\DataContainer\PaletteManipulator;

    /**
     * Palettes
     */
    $GLOBALS['TL_DCA']['tl_module']['palettes']['iso_productlist_direct']       = $GLOBALS['TL_DCA']['tl_module']['palettes']['iso_productlist'];
    $GLOBALS['TL_DCA']['tl_module']['palettes']['iso_productfilter_direct']     = $GLOBALS['TL_DCA']['tl_module']['palettes']['iso_productfilter'];

    PaletteManipulator::create()
        ->addField('iso_searchFields', 'config_legend', PaletteManipulator::POSITION_PREPEND)
        ->removeField('iso_filterModules', 'config_legend')
        ->applyToPalette('iso_productlist_direct', 'tl_module')

        ->addField('iso_filterTypes', 'config_legend', PaletteManipulator::POSITION_PREPEND)
        ->removeField('iso_filterFields', 'config_legend')
        ->removeField('iso_searchFields', 'config_legend')
        ->applyToPalette('iso_productfilter_direct', 'tl_module')
    ;


    /**
     * Fields
     */
    $GLOBALS['TL_DCA']['tl_module']['fields']['iso_filterTypes'] = array
    (
        'label'						=> &$GLOBALS['TL_LANG']['tl_module']['iso_filterTypes'],
        'exclude'					=> true,
        'inputType'					=> 'checkboxWizard',
        'options_callback'			=> array('IsotopeDirect\Backend\Filter\Callback', 'getFilterTypes'),
        'eval'						=> array('multiple'=>true, 'tl_class'=>'w50 w50h clr'),
        'sql'						=> "blob NULL",
    );
}
