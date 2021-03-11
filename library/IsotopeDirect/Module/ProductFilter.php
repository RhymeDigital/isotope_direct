<?php

/**
 * Copyright (C) 2015 Rhyme Digital, LLC
 * 
 * @author		Blair Winans <blair@rhyme.digital>
 * @author		Adam Fisher <adam@rhyme.digital>
 * @link		http://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


namespace IsotopeDirect\Module;

use IsotopeDirect\Filter\Filter;

use Isotope\Isotope;
use Isotope\Model\Product as Product_Model;
use Isotope\Module\Module as Isotope_Module;
use Haste\Haste;
use Haste\Http\Response\JsonResponse;


/**
 * Class ProductFilter
 * A more direct version of the product filter
 */
class ProductFilter extends Isotope_Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'iso_filter_direct_default';

    /**
     * Form ID
     * @var string
     */
    protected $strFormIdPrefix = 'iso_filter_';
    
    /**
     * Global categories for filters
     * @var array
     */
    protected $arrCategories;

    /**
     * Display a wildcard in the back end
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            $objTemplate = new \BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### ISOTOPE ECOMMERCE: PRODUCT FILTERS - DIRECT ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        $this->generateAjax();

        // Hide filters in reader mode if the respective setting is enabled
        if ($this->iso_hide_list && \Input::get('product') != '')
        {
            return '';
        }
        
        // Override the template
        if ($this->iso_filterTpl)
        {
	        $this->strTemplate = $this->iso_filterTpl;
        }

        $strBuffer = parent::generate();

        return $strBuffer;
    }


    /**
     * Generate the module
     * @return void
     */
    protected function compile()
    {
    	global $objPage;
    	$this->loadDataContainer('tl_iso_product');
    	
    	//Get initial categories
    	$this->arrCategories = $this->findCategories();
    
    	//Handle requests before generating as we will likely redirect
    	if (\Input::post('FORM_SUBMIT') == $this->strFormIdPrefix . $this->id)
    	{
    		$this->handleRequests();
		}
		
		//Generate the filters if there are no request redirects
    	$objTemplate = $this->Template;
    	
    	$arrFilterTypes = deserialize($this->iso_filterTypes, true);
    	
    	foreach ($arrFilterTypes as $filterType)
    	{
	    	if (!isset($GLOBALS['PRODUCT_FILTERS'][$filterType]))
	    	{
		    	continue;
	    	}
	    	
			$strClass = $GLOBALS['PRODUCT_FILTERS'][$filterType]['class'];
			
			if (!class_exists($strClass))
			{
				continue;
			}
	    	
	    	$strClass::generateFilter($this->arrCategories, $objTemplate, $this);
    	}
    	
    	$this->Template = $objTemplate;
        $this->Template->id = $this->id;
        $this->Template->formId = $this->strFormIdPrefix . $this->id;
        $this->Template->actionClear = \Controller::generateFrontendUrl($objPage->row());
        $this->Template->clearLabel = $GLOBALS['TL_LANG']['MSC']['clearFiltersLabel'];
        $this->Template->slabel = $GLOBALS['TL_LANG']['MSC']['submitLabel'];
    }
    
    
    /**
     * Handle incoming filter requests and generate appropriate URL
     * @return void
     */
    protected function handleRequests()
    {
    	if (!$this->jumpTo)
    	{
	    	global $objPage;
    	}
    	else
    	{
	    	$objPage = \PageModel::findWithDetails($this->jumpTo);
    	}
    	
    	$arrRedirectParams = array();
    	$strRedirectParams = '';
    	
    	$arrFilterTypes = deserialize($this->iso_filterTypes, true);
    	
    	// Loop through each filter type to get the URL params
    	foreach ($arrFilterTypes as $filterType)
    	{
	    	if (!isset($GLOBALS['PRODUCT_FILTERS'][$filterType]))
	    	{
		    	continue;
	    	}
	    	
			$strClass = $GLOBALS['PRODUCT_FILTERS'][$filterType]['class'];
			
			if (!class_exists($strClass))
			{
				continue;
			}
	    	
	    	$varReturn = $strClass::generateFilter($this->arrCategories, $objTemplate, $this, true);
	    	
	    	if(!empty($varReturn))
	    	{
	    		$arrRedirectParams[] = $varReturn;
	    	}
    	}
    	
    	// Get any previously submitted URL params that aren't updated by this module
    	$arrKeys = array_diff(array_keys($GLOBALS['PRODUCT_FILTERS']), $arrFilterTypes);
    	
    	foreach ($arrKeys as $key)
    	{
	    	if (\Input::get($key))
	    	{
	    		$arrRedirectParams[] = $key . '/' . Filter::uncleanChars(\Input::get($key));
	    	}
    	}
    	
    	$strRedirectParams = implode('/', $arrRedirectParams);
  	
    	$strRedirect = \Controller::generateFrontendUrl($objPage->row(), (strlen($strRedirectParams) ? '/' . $strRedirectParams : null) );
    	
    	\Controller::redirect($strRedirect);
    }


    /**
     * Generate a limit form
     */
    protected function generateLimit()
    {
    }

    /**
     * Generate ajax
     *
     * @throws \Exception
     */
    public function generateAjax()
    {
        if (!\Environment::get('isAjaxRequest')) {
            return;
        }

        if ($this->iso_searchAutocomplete && \Input::get('iso_autocomplete') == $this->id) {
            $objProducts = Product_Model::findPublishedByCategories($this->findCategories(), ['order' => 'c.sorting']);

            if (null === $objProducts) {
                $objResponse = new JsonResponse([]);
                $objResponse->send();
                exit;
            }

            $objResponse = new JsonResponse(array_values($objProducts->fetchEach($this->iso_searchAutocomplete)));
            $objResponse->send();
        }
    }


    /**
     * The ids of all pages we take care of. This is what should later be used eg. for filter data.
     *
     * @return array
     */
    protected function findCategories(array &$arrFilters = null)
    {
        if (null === $this->arrCategories) {

            if ($this->defineRoot && $this->rootPage > 0) {
                $objPage = \PageModel::findWithDetails($this->rootPage);
            } else {
                global $objPage;
            }

            $t = \PageModel::getTable();
            $arrCategories = null;
            $arrUnpublished = array();
            $strWhere = "$t.type!='error_403' AND $t.type!='error_404'";

            if (!BE_USER_LOGGED_IN) {
                $time = time();
                $objUnpublished = \PageModel::findBy(array("($t.start!='' AND $t.start>$time) OR ($t.stop!='' AND $t.stop<$time) OR $t.published=?"), array(''));
                $arrUnpublished = $objUnpublished->fetchEach('id');
                //$strWhere .= " AND ($t.start='' OR $t.start<$time) AND ($t.stop='' OR $t.stop>$time) AND $t.published='1'";
            }

            switch ($this->iso_category_scope) {

                case 'global':
                    $arrCategories = array($objPage->rootId);
                    $arrCategories = \Database::getInstance()->getChildRecords($objPage->rootId, 'tl_page', false, $arrCategories, $strWhere);
                    $arrCategories = array_diff($arrCategories, $arrUnpublished);
                    break;

                case 'current_and_first_child':
                    $arrCategories   = \Database::getInstance()->execute("SELECT id FROM tl_page WHERE pid={$objPage->id} AND $strWhere")->fetchEach('id');
                    $arrCategories[] = $objPage->id;
                    break;

                case 'current_and_all_children':
                    $arrCategories = array($objPage->id);
                    $arrCategories = \Database::getInstance()->getChildRecords($objPage->id, 'tl_page', false, $arrCategories, $strWhere);
                    $arrCategories = array_diff($arrCategories, $arrUnpublished);
                    break;

                case 'parent':
                    $arrCategories = array($objPage->pid);
                    break;

                case 'product':
                    /** @var \Isotope\Model\Product\Standard $objProduct */
                    $objProduct = Product_Model::findAvailableByIdOrAlias(\Haste\Input\Input::getAutoItem('product'));

                    if ($objProduct !== null) {
                        $arrCategories = $objProduct->getCategories(true);
                    } else {
                        $arrCategories = array(0);
                    }
                    break;

                case 'article':
                    $arrCategories = array($GLOBALS['ISO_CONFIG']['current_article']['pid'] ? : $objPage->id);
                    break;

                case '':
                case 'current_category':
                    $arrCategories = array($objPage->id);
                    break;

                default:
                    if (isset($GLOBALS['ISO_HOOKS']['findCategories']) && is_array($GLOBALS['ISO_HOOKS']['findCategories'])) {
                        foreach ($GLOBALS['ISO_HOOKS']['findCategories'] as $callback) {
                            $objCallback   = \System::importStatic($callback[0]);
                            $arrCategories = $objCallback->$callback[1]($this);

                            if ($arrCategories !== false) {
                                break;
                            }
                        }
                    }
                    break;
            }

            $this->arrCategories = empty($arrCategories) ? array(0) : $arrCategories;
        }

        return $this->arrCategories;
    }
}
