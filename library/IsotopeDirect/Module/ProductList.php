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

use Contao\Model\QueryBuilder;

use IsotopeDirect\Filter\Filter;

use Haste\Haste;
use Haste\Input\Input;
use Haste\Generator\RowClass;
use Haste\Http\Response\HtmlResponse;

use Isotope\Isotope;
use Isotope\Frontend as Isotope_Frontend;
use Isotope\Model\Product as Product_Model;
use Isotope\Module\ProductList as Isotope_ProductList;



/**
 * Class ProductList
 * A more direct version of the product list
 */
class ProductList extends Isotope_ProductList
{
    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_productlist_direct';

    /**
     * Cache items. Can be disable in a child class, e.g. a "random items list"
     * @var boolean
     */
    protected $blnCacheProperties = false;

    /**
     * Display a wildcard in the back end
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            $objTemplate = new \BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### ISOTOPE ECOMMERCE: PRODUCT LIST - DIRECT ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }
        
        // Hide item list in reader mode if the respective setting is enabled
        if ($this->iso_hide_list && Input::getAutoItem('product', false, true) != '')
        {
            return '';
        }
        
        if (is_numeric(\Input::get('perpage')) && intval(\Input::get('perpage')))
        {
	        $this->perPage = intval(\Input::get('perpage'));
        }

        return parent::generate();
    }


    /**
     * Generate a single item and return it's HTML string
     * TEMPORARY UNTIL WE UPDATE THE AJAX STUFF
     * @return string
     */
    public function generateAjax()
    {
        $objItem = Isotope_Frontend::getProduct(\Input::get('item'));

        if ($objItem !== null)
        {
            return $objItem->generateAjax($this);
        }

        return '';
    }


    /**
     * Compile item list.
     *
     * This function is specially designed so you can keep it in your child classes and only override findProperties().
     * You will automatically gain item caching (see class property), grid classes, pagination and more.
     *
     * @return void
     */
    protected function compile()
    {
        
        //Get products
        $arrProducts = $this->findProducts();

        // No products found
        if (!is_array($arrProducts) || empty($arrProducts)) {
            $this->compileEmptyMessage();

            return;
        }

        $arrBuffer = array();

        $arrDefaultOptions = $this->getDefaultProductOptions();

        foreach ($arrProducts as $objProduct) {
            $arrConfig = array(
                'module'        => $this,
                'template'      => ($this->iso_list_layout ?: $objProduct->getRelated('type')->list_template),
                'gallery'       => ($this->iso_gallery ?: $objProduct->getRelated('type')->list_gallery),
                'buttons'       => deserialize($this->iso_buttons, true),
                'useQuantity'   => $this->iso_use_quantity,
                'jumpTo'        => $this->findJumpToPage($objProduct),
            );

            if (\Environment::get('isAjaxRequest') && \Input::post('AJAX_MODULE') == $this->id && \Input::post('AJAX_PRODUCT') == $objProduct->getProductId()) {
                $objResponse = new HtmlResponse($objProduct->generate($arrConfig));
                $objResponse->send();
            }

            $objProduct->mergeRow($arrDefaultOptions);

            // Must be done after setting options to generate the variant config into the URL
            if ($this->iso_jump_first && \Haste\Input\Input::getAutoItem('product') == '') {
                \Controller::redirect($objProduct->generateUrl($arrConfig['jumpTo']));
            }

            $arrCSS = deserialize($objProduct->cssID, true);

            $arrBuffer[] = array(
                'cssID'     => ($arrCSS[0] != '') ? ' id="' . $arrCSS[0] . '"' : '',
                'class'     => trim('product ' . ($objProduct->isNew() ? 'new ' : '') . $arrCSS[1]),
                'html'      => $objProduct->generate($arrConfig),
                'product'   => $objProduct,
            );
        }

        // HOOK: to add any product field or attribute to mod_iso_productlist template
        if (isset($GLOBALS['ISO_HOOKS']['generateProductList']) && is_array($GLOBALS['ISO_HOOKS']['generateProductList'])) {
            foreach ($GLOBALS['ISO_HOOKS']['generateProductList'] as $callback) {
                $objCallback = \System::importStatic($callback[0]);
                $arrBuffer   = $objCallback->$callback[1]($arrBuffer, $arrProducts, $this->Template, $this);
            }
        }

        RowClass::withKey('class')->addCount('product_')->addEvenOdd('product_')->addFirstLast('product_')->addGridRows($this->iso_cols)->addGridCols($this->iso_cols)->applyTo($arrBuffer);

        $this->Template->products = $arrBuffer;
                
    }


    /**
     * Find all products we need to list.
     * @return array
     */
    protected function findProducts($arrCacheIds=null)
    {
        $arrColumns    = array();
        $arrCategories = $this->findCategories();
		
		//Get filters and sorting values
        list($arrValues, $strWhere, $strSorting) = $this->getFiltersAndSorting();
		
		//Handle no values
        if (!is_array($arrValues)) {
            $arrValues = array();
        }

        //Add categories to query
	    $arrColumns[] = Product_Model::getTable() . ".id IN( SELECT pid FROM tl_iso_product_category WHERE page_id IN (" . implode(',', $arrCategories) . "))"; 
		
		//Get only cache IDs
        if (!empty($arrCacheIds) && is_array($arrCacheIds)) {
            $arrColumns[] = Product_Model::getTable() . ".id IN (" . implode(',', $arrCacheIds) . ")";
        }

        // Apply new/old product filter
        if ($this->iso_newFilter == 'show_new') {
            $arrColumns[] = Product_Model::getTable() . ".dateAdded>=" . Isotope::getConfig()->getNewProductLimit();
        } elseif ($this->iso_newFilter == 'show_old') {
            $arrColumns[] = Product_Model::getTable() . ".dateAdded<" . Isotope::getConfig()->getNewProductLimit();
        }

        if ($this->iso_list_where != '') {
            $arrColumns[] = Haste::getInstance()->call('replaceInsertTags', $this->iso_list_where);
        }
        
        //Add where query from filters/sorting
        if ($strWhere != '') {
            $arrColumns[] = $strWhere;
        }
		
		//Calculate the total on the query
        $intTotal = static::countPublishedBy($arrColumns, $arrValues);
	
		//Generate pagination and get offset
		$offset = $this->generatePagination($intTotal);

		//Build options
		$arrOptions = array
		(
			'offset'	=> $offset,
			'limit'		=> ($this->numberOfItems && $this->perPage) ? min($this->numberOfItems, $this->perPage) : ($this->perPage ?: $this->numberOfItems),
			'order'		=> $strSorting,
		);
		
		// Temporary fix for category sorting values
	    $arrColumns[] = "c.page_id IN (" . implode(',', $arrCategories) . ")";
		
		//Run query
        $objProducts = Product_Model::findPublishedBy(
            $arrColumns,
            $arrValues,
            $arrOptions
        );

        return (null === $objProducts) ? array() : $objProducts->getModels();
    }


    /**
     * Generate the pagination
     * @param integer
     * @return integer
     */
    protected function generatePagination($total)
    {
        // Add pagination
        if ($this->perPage > 0 && $total > 0)
        {
            $page = \Input::get('page') ? \Input::get('page') : 1;

            // Check the maximum page number
            if ($page > ($total/$this->perPage))
            {
                $page = ceil($total/$this->perPage);
            }

            $offset = ($page - 1) * $this->perPage;

            $objPagination = new \Pagination($total, $this->perPage);
            $this->Template->pagination = $objPagination->generate("\n  ");

            return $offset;
        }

        return 0;
    }
    
    
    
    /**
     * Get filter & sorting configuration
     * @return array
     */
    protected function getFiltersAndSorting($blnNativeSQL = true)
    {
    	$strWhere 	= '';
    	$strSorting = '';
    	$arrWhere	= array();
    	$arrValues 	= array();
    	$blnDefaultSort = false;
    	
    	// Sorting
    	if (\Input::get('sorting'))
    	{
    		$arrSortField = explode('-', \Input::get('sorting'));
    		
	    	// Needs to be a field value in tl_iso_product and either be asc or desc
	    	if (\Database::getInstance()->fieldExists($arrSortField[0], Product_Model::getTable()) && (strtolower($arrSortField[1])=='asc' || strtolower($arrSortField[1])=='desc'))
	    	{
		    	$strSorting = $arrSortField[0] . ' ' . strtoupper($arrSortField[1]);
	    	}
	    	
	    	// Price sorting isn't on tl_iso_product
	    	if (strpos(\Input::get('sorting'), 'price') === 0 && (strtolower($arrSortField[1])=='asc' || strtolower($arrSortField[1])=='desc'))
	    	{
		    	$strSorting = "(SELECT IFNULL(ppt.price, 0) FROM tl_iso_product_pricetier ppt INNER JOIN tl_iso_product_price pp ON ppt.pid=pp.id WHERE pp.pid=tl_iso_product.id) " . strtoupper($arrSortField[1]);
	    	}
    	}
    	
    	// Default sorting
    	if (!$strSorting && $this->iso_listingSortField && $this->iso_listingSortDirection)
    	{
    		$blnDefaultSort = true;
	    	$strSorting = $this->iso_listingSortField . ' ' . $this->iso_listingSortDirection;
	    	
    	}

    	// Price range
    	if (\Input::get('pricerange'))
    	{
    		$arrTempWhere = array();
    		$arrGet = array_map(array('IsotopeDirect\Filter\Filter', 'uncleanChars'), explode(',', \Input::get('pricerange')));
    		
    		foreach ($arrGet as $get)
    		{
	    		$arrRange = trimsplit('to', $get);
	    		
	    		if (empty($arrRange))
	    		{
		    		continue;
	    		}
	    		
	    		// todo: add config_id, member_group, start, stop
	    		$strRangeWhere = "SELECT p.pid AS `product_id` FROM tl_iso_product_price p
									INNER JOIN tl_iso_product_pricetier pt
										ON p.id = pt.pid
									WHERE pt.min = 1";
									
		    	$strRangeWhere .= " AND pt.price >= " . intval($arrRange[0]);
	    		
	    		if (count($arrRange) > 1 && !empty($arrRange[1]))
	    		{
			    	$strRangeWhere .= " AND pt.price < " . intval($arrRange[1]);
	    		}
	    		
	    		$objResult = \Database::getInstance()->executeUncached($strRangeWhere);
	    		
	    		if ($objResult->numRows)
	    		{
	    			$arrTempWhere[] = Product_Model::getTable() . ".id IN (".implode(',', $objResult->fetchEach('product_id')).")";
	    		}
    		}
    		
    		if (count($arrTempWhere) > 0)
    		{
    			//Note that this filter is an OR
			    $arrWhere['pricerange'] = '(' . implode(" OR ", $arrTempWhere) . ')';
    		}
    	}
    	
		$arrSortFields = array();
		$arrSortValues = array();
    	
    	// Keywords
    	if (\Input::get('keywords'))
    	{
    		$arrFields = deserialize($this->iso_searchFields, true);
    		
    		if (count($arrFields))
    		{
    			$where = array();
    			include_once(TL_ROOT . '/system/modules/isotope_direct/config/stopwords.php');
    			
	    		$arrKeywords = array_map(array('IsotopeDirect\Filter\Filter', 'uncleanChars'), explode(',', \Input::get('keywords')));
    			
    			foreach ($arrKeywords as $keyword)
    			{
    				// Look for all words in the fields
	    			$arrFinalKeywords = explode(' ', $keyword);
	    			
	    			foreach ($arrFinalKeywords as $finalKeyword)
	    			{
	    				$strTerm = trim($finalKeyword);
	    				
	    				if (empty($strTerm) || in_array(strtolower($strTerm), array_map('strtolower', $GLOBALS['KEYWORD_STOP_WORDS'])) || in_array(strtolower($strTerm), array_map('strtolower', $GLOBALS['KEYWORD_STOP_WORDS'])))
	    				{
		    				continue;
	    				}
	    				
			    		foreach ($arrFields as $field)
			    		{
				    		$where[] = Product_Model::getTable() . ".$field REGEXP ?";
					    	$arrValues[] = $strTerm;
			    		}
	    			}
	    			
			    	// Do relevancy sorting
	    			$intPriority = 1;
		    		foreach ($arrFields as $field)
		    		{
		    			foreach ($arrFinalKeywords as $finalKeyword)
		    			{
		    				$strTerm = trim($finalKeyword);
		    				
		    				if (empty($strTerm) || in_array(strtolower($strTerm), array_map('strtolower', $GLOBALS['KEYWORD_STOP_WORDS'])) || in_array(strtolower($strTerm), array_map('strtolower', $GLOBALS['KEYWORD_STOP_WORDS'])))
		    				{
			    				continue;
		    				}
		    				
				    		$arrSortFields[] = "CASE WHEN " . Product_Model::getTable() . ".$field REGEXP ? THEN $intPriority ELSE 9999999999 END";
					    	$arrSortValues[] = $strTerm;
						    $intPriority++;
					    }
		    		}
    			}
	    		
	    		if (count($where))
	    		{
			    	$arrWhere['keywords'] = '('.implode(' OR ', $where).')';
			    	
			    }
    		}
    	}

        // !HOOK: custom filter/sorting types
        if (isset($GLOBALS['ISO_HOOKS']['processFiltersAndSorting']) && is_array($GLOBALS['ISO_HOOKS']['processFiltersAndSorting']))
        {
            foreach ($GLOBALS['ISO_HOOKS']['processFiltersAndSorting'] as $callback)
            {
                $objCallback = \System::importStatic($callback[0]);
                list($arrValues, $arrWhere, $strSorting) = $objCallback->$callback[1]($arrValues, $arrWhere, $strSorting, $this->findCategories(), $this);
            }
        }
        
    	// Do relevancy sorting - todo: need a better way to do this so it can be passed to the hook
    	if ($blnDefaultSort && !empty($arrSortFields) && !empty($arrSortValues))
    	{
	    	$strSorting = implode(',', $arrSortFields);
	    	
	    	foreach ($arrSortValues as $val)
	    	{
		    	$arrValues[] = $val;
	    	}
    	}
    	
    	// Sort by category if nothing else
    	$strSorting = $strSorting ?: "c.sorting ".$this->iso_listingSortDirection;
    	    	
    	// Now put together the entire WHERE
    	if(count($arrWhere) > 0)
    	{
	    	$strWhere = implode(' AND ', $arrWhere);
    	}

    	return array($arrValues, $strWhere, $strSorting);
    }
    
    
    /**
     * Find published products by condition
     * @param   mixed
     * @param   mixed
     * @param   array
     * @return  \Contao\Collection
     */
    public static function countPublishedBy($arrColumns, $arrValues, $arrOptions=array())
    {
        $p = Product_Model::getTable();

        $arrValues = (array) $arrValues;

        if (!is_array($arrColumns)) {
            $arrColumns = array();
        }
        
        if (BE_USER_LOGGED_IN !== true) {
            $time = time();
            $arrColumns[] = "$p.published='1' AND ($p.start='' OR $p.start<$time) AND ($p.stop='' OR $p.stop>$time)";
        }
        
        $arrFind = array_merge(array
        (
        	'table'			=> $p,
        	'column'		=> $arrColumns,
        	'value'			=> $arrValues,
        ), (array)$arrOptions);
        
        $strQuery = QueryBuilder::find($arrFind);
        $strQuery = static::replaceSectionsOfString($strQuery, "SELECT ", "FROM ", "SELECT $p.id FROM ", true, false);
        
        $arrIDs = \Database::getInstance()->prepare($strQuery)->execute($arrValues)->fetchEach('id');
        
        // !HOOK: custom actions
        if (isset($GLOBALS['ISO_HOOKS']['passFoundProducts']) && is_array($GLOBALS['ISO_HOOKS']['passFoundProducts']))
        {
            foreach ($GLOBALS['ISO_HOOKS']['passFoundProducts'] as $callback)
            {
                $objCallback = \System::importStatic($callback[0]);
                $objCallback->$callback[1]($arrIDs);
            }
        }
          
        return (int) count($arrIDs);
    }


    /**
     * The ids of all pages we take care of. This is what should later be used eg. for filter data.
     *
     * @return array
     */
    protected function findCategories()
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

	
	/**
	 * Remove sections of a string using a start and end (use "[caption" and "]" to remove any caption blocks)
	 * @param  string
	 * @param  string
	 * @param  string
	 * @return string
	 */
	public static function replaceSectionsOfString($strSubject, $strStart, $strEnd, $strReplace='', $blnCaseSensitive=true, $blnRecursive=true)
	{
		// First index of start string
		$varStart = $blnCaseSensitive ? strpos($strSubject, $strStart) : stripos($strSubject, $strStart);
		
		if ($varStart === false)
			return $strSubject;
		
		// First index of end string
		$varEnd = $blnCaseSensitive ? strpos($strSubject, $strEnd, $varStart+1) : stripos($strSubject, $strEnd, $varStart+1);
		
		// The string including the start string, end string, and everything in between
		$strFound = $varEnd === false ? substr($strSubject, $varStart) : substr($strSubject, $varStart, ($varEnd + strlen($strEnd) - $varStart));
		
		// The string after the replacement has been made
		$strResult = $blnCaseSensitive ? str_replace($strFound, $strReplace, $strSubject) : str_ireplace($strFound, $strReplace, $strSubject);
		
		// Check for another occurence of the start string
		$varStart = $blnCaseSensitive ? strpos($strSubject, $strStart) : stripos($strSubject, $strStart);
		
		// If this is recursive and there's another occurence of the start string, keep going
		if ($blnRecursive && $varStart !== false)
		{
			$strResult = static::replaceSectionsofString($strResult, $strStart, $strEnd, $strReplace, $blnCaseSensitive, $blnRecursive);
		}
		
		return $strResult;
	}

}
