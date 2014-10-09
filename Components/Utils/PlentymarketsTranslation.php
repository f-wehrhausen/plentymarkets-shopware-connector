<?php
/**
 * Created by IntelliJ IDEA.
 * User: ioana
 * Date: 29/09/14
 * Time: 11:08
 */

class PlentymarketsTranslation 
{
	/**
	 *
	 * @var PlentymarketsTranslation
	 */
	protected static $Instance;


	/**
	 * I am the singleton method
	 *
	 * @return PlentymarketsTranslation
	 */
	public static function getInstance()
	{
		if (!self::$Instance instanceof self)
		{
			self::$Instance = new self();
		}
		return self::$Instance;
	}
	
	/**
	 * @description Get the current language of the shop with id = shopId
	 * @param int $shopId
	 * @return array
	 */
	public static function getShopMainLanguage($shopId)
	{
		/** @var $shopRepositoryList Shopware\Models\Shop\Repository */
		$shopRepositoryList = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');
		
		/** @var $shopRepository Shopware\Models\Shop\Shop */
		$shopRepository = $shopRepositoryList->getActiveById($shopId);
		
		$mainLang[$shopRepository->getLocale()->getId()] = array( 	'language' => $shopRepository->getLocale()->getLanguage(),
																	'locale' => $shopRepository->getLocale()->getLocale(),
																	'mainShopId' => NULL); // the main shop has no main shop Id => only language shops have a main shop ID !! TB: s_core_shops

		return $mainLang;
	}

	/**
	 * @description Convert the langugae format for plenty (e.g en_GB  = en)
	 * @param string $locale
	 * @return string
	 */
	public static function getPlentyLocaleFormat($locale)
	{
		$parts = explode('_',$locale);
		
		return $parts[0];
	}
	
	/**
	 * @description Get all active languages (main language und all other activated languages) of the shop with id = shopId
	 * @param int $shopId
	 * @return array
	 */
	public static function getShopActiveLanguages($shopId)
	{

		// array for saving the languages of the shop
		$activeLanguages = array();
		
		
		// add the main language shop
		$mainLang = PlentymarketsTranslation::getInstance()->getShopMainLanguage($shopId);
		
		$activeLanguages[key($mainLang)] = array_pop($mainLang);
		
		/** @var $shopRepositoryList Shopware\Models\Shop\Repository */
		$shopRepositoryList = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');
		
		// get all language shops of the shop with id = $shopId =>  find all shops where mainId = shopId 
		$languageShops = $shopRepositoryList->findBy(array('mainId' => $shopId));

		/** @var $languageShop Shopware\Models\Shop\Shop */
		foreach($languageShops as $languageShop)
		{	
			// locale id = language id in shopware !! 
			$activeLanguages[$languageShop->getLocale()->getId()] = array(	'language' => $languageShop->getLocale()->getLanguage(), // e.g language = Englisch
																			'locale' => $languageShop->getLocale()->getLocale(), // e.g locale = en_GB 
																			'mainShopId' => $shopId);  
		}
		
		return $activeLanguages;
	}

	/**
	 * @description Get the language infos of the locale from shopware
	 * @param string locale
	 * @return array
	 */
	public static function getLanguageByLocale($locale)
	{
		/** @var $locales */
		$locales = Shopware()->Models()->getRepository('Shopware\Models\Shop\Locale')->findBy(array('locale' => $locale));

		$languages = array();

		/** @var  $locale Shopware\Models\Shop\Locale */
		foreach($locales as $locale)
		{
			$languages[$locale->getId()] = array(	'language' => $locale->getLanguage(),
													'locale' => $locale->getLocale());
		}

		return $languages;

	}
	
	/**
	 * @description Get all languages from shopware
	 * @return array
	 */
	public static function getAllLanguages()
	{
		/** @var $locales */
		$locales = Shopware()->Models()->getRepository('Shopware\Models\Shop\Locale')->findAll();

		$languages = array();

		/** @var  $locale Shopware\Models\Shop\Locale */
		foreach($locales as $locale)
		{
			$languages[$locale->getId()] = array('language' => $locale->getLanguage(),
												 'locale' => $locale->getLocale());
		}
		
		return $languages;
	
	}

	/**
	 * @description Get the translation of the object
	 * @param int $langId
	 * @param int $mainId
	 * @return int
	 */
	public static function getLanguageShopID($langId, $mainId)
	{
		/** @var $shopRepositoryList Shopware\Models\Shop\Repository */
		$shopRepositoryList = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');

		// get the language shop id by language Id and main shop Id  
		/** @var $shop Shopware\Models\Shop\Shop */
	//	$shop = $shopRepositoryList->findBy(array('locale' => $langId));
		
		try
		{
			$sql = 'SELECT id
				FROM s_core_shops
				WHERE locale_id ='. $langId . 
				' AND main_id = '. $mainId;

			$shopId = Shopware()->Db()->query($sql)->fetchAll();
			
		}catch (Exception $e)
		{
			$shopId = null;
		}
		
		return $shopId[0]['id'];
	}

	/**
	 * @description Get the translation of the object from a language shop
	 * @param int $mainShopId
	 * @param string $type
	 * @param int $objectId
	 * @param int $langId
	 * @return array
	 */
	public static function getShopwareTranslation($mainShopId, $type, $objectId, $langId)
	{
		$translation = null;
		
		/** @var $locale Shopware\Models\Translation\Translation */
		$localeRepository = Shopware()->Models()->getRepository('Shopware\Models\Translation\Translation');
		
		if(!is_null(PlentymarketsTranslation::getInstance()->getLanguageShopID($langId, $mainShopId)))
		{
			// get the language shop Id
			$shopId = PlentymarketsTranslation::getInstance()->getLanguageShopID($langId, $mainShopId);
		}
		else
		{	// the shop id is the main shop id => try to get translation of the object for the main shop (e.g attribute translation)
			$shopId = $mainShopId;
		}
		
		try
		{
			// in s_core_translation the objectlanguage = shopId !!!!! 
			$keyData = $localeRepository->findOneBy(array( 	'type' => $type,
															'key' => $objectId,
															'localeId' => $shopId)); // localeId = objectlanguage = shopId ONLY for this method, otherwise localeId = languageID (TB: s_core_locales )  !!!! 
			
			if(method_exists($keyData, 'getData'))
			{
				$serializedTranslation = $keyData->getData();
				$translation = unserialize( $serializedTranslation);
			}
					
		}catch(Exception $e)
		{
			$translation = null;
		}
		
		return $translation;
	}

	/**
	 * @description Set the translation for the object for the language shops
	 * @param string $type
	 * @param int $objectId
	 * @param int $languageShopId
	 * @param array $data
	 */
	public static function setShopwareTranslation($type, $objectId, $languageShopId, $data)
	{
		// !!! objectlanguage = language shopId 
		// !!! objectkey = object Id (e.g. article Id)
		$sql = 'INSERT INTO `s_core_translations` (
				`objecttype`, `objectdata`, `objectkey`, `objectlanguage`
				) VALUES (
				?, ?, ?, ?
				) ON DUPLICATE KEY UPDATE `objectdata`=VALUES(`objectdata`);
				';
		
		 Shopware()->Db()->query($sql, array($type, serialize($data), $objectId, $languageShopId));

	}

	
} 