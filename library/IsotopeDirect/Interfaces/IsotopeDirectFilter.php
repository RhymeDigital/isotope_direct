<?php

/**
 * Copyright (C) 2021 Rhyme Digital, LLC
 *
 * @author		Blair Winans <blair@rhyme.digital>
 * @author		Adam Fisher <adam@rhyme.digital>
 * @link		https://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace IsotopeDirect\Interfaces;

use Contao\Template;
use Contao\Module;

/**
 * Interface IsotopeDirectFilter
 * Base class for IsotopeDirect filters
 */
interface IsotopeDirectFilter
{

    /**
     * Add this filter to the module's template or get the URL params
     * @param array $arrCategories
     * @param Template $objTemplate
     * @param Module $objModule
     * @param bool $blnGenURL
     * @return mixed
     */
    public static function generateFilter(array &$arrCategories, Template &$objTemplate, Module $objModule, bool $blnGenURL=false);

}
