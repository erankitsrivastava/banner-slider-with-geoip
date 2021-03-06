<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Bannerslider
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\BannerSlider\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\BannerSlider\Model\BannerFactory;
use Mageplaza\BannerSlider\Model\SliderFactory;
use Mageplaza\Core\Helper\AbstractData;

/**
 * Class Data
 * @package Mageplaza\BannerSlider\Helper
 */
class Data extends AbstractData
{
    const CONFIG_MODULE_PATH = 'mpbannerslider';

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var HttpContext
     */
    protected $httpContext;

    /**
     * @var BannerFactory
     */
    public $bannerFactory;

    /**
     * @var SliderFactory
     */
    public $sliderFactory;

    /**
     * Data constructor.
     *
     * @param DateTime $date
     * @param Context $context
     * @param HttpContext $httpContext
     * @param BannerFactory $bannerFactory
     * @param SliderFactory $sliderFactory
     * @param StoreManagerInterface $storeManager
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        DateTime $date,
        Context $context,
        HttpContext $httpContext,
        BannerFactory $bannerFactory,
        SliderFactory $sliderFactory,
        StoreManagerInterface $storeManager,
        \MagePal\GeoIp\Service\GeoIpService $geoIpService,
        ObjectManagerInterface $objectManager
    )
    {
        $this->date          = $date;
        $this->httpContext   = $httpContext;
        $this->bannerFactory = $bannerFactory;
        $this->sliderFactory = $sliderFactory;
        $this->geoIpService = $geoIpService;
        parent::__construct($context, $objectManager, $storeManager);
    }

    /**
     * @param null $slider
     *
     * @return false|string
     */
    public function getBannerOptions($slider = null)
    {
        if ($slider && $slider->getDesign() === "1") { //not use Config
            $config = $slider->getData();
        } else {
            $config = $this->getModuleConfig('mpbannerslider_design');
        }

        $defaultOpt    = $this->getDefaultConfig($config);
        $responsiveOpt = $this->getResponsiveConfig($slider);
        $effectOpt     = $this->getEffectConfig($slider);

        $sliderOptions = array_merge($defaultOpt, $responsiveOpt, $effectOpt);

        return self::jsonEncode($sliderOptions);
    }

    /**
     * @param $configs
     *
     * @return array
     */
    public function getDefaultConfig($configs)
    {
        $basicConfig = [];
        foreach ($configs as $key => $value) {
            if (in_array($key, ['autoWidth', 'autoHeight', 'loop', 'nav', 'dots', 'lazyLoad', 'autoplay', 'autoplayTimeout'])) {
                $basicConfig[$key] = (int)$value;
            }
        }

        return $basicConfig;
    }

    /**
     * @param null $slider
     *
     * @return array
     */
    public function getResponsiveConfig($slider = null)
    {
        if (is_null($slider)) {
            $isResponsive = $this->getModuleConfig('mpbannerslider_design/responsive') == 1;
            try {
                $responsiveItems = $this->unserialize($this->getModuleConfig('mpbannerslider_design/item_slider'));
            } catch (\Exception $e) {
                $responsiveItems = [];
            }
        } else {
            $isResponsive = $slider->getIsResponsive();
            try {
                $responsiveItems = $this->unserialize($slider->getResponsiveItems());
            } catch (\Exception $e) {
                $responsiveItems = [];
            }
        }
        if (!$isResponsive || !$responsiveItems) {
            return ["items" => 1];
        }

        $result = [];
        foreach ($responsiveItems as $config) {
            $size          = $config['size'] ?: 0;
            $items         = $config['items'] ?: 0;
            $result[$size] = ["items" => $items];
        }

        return ['responsive' => $result];
    }

    /**
     * @param $slider
     *
     * @return array
     */
    public function getEffectConfig($slider)
    {
        if (!$slider) {
            return [];
        }

        return ['animateOut' => $slider->getEffect()];
    }

    /**
     * @param null $id
     *
     * @return \Mageplaza\BannerSlider\Model\ResourceModel\Banner\Collection
     */
    public function getBannerCollection($id = null)
    {
        $collection = $this->bannerFactory->create()->getCollection();

        $collection->join(
            ['banner_slider' => $collection->getTable('mageplaza_bannerslider_banner_slider')],
            'main_table.banner_id=banner_slider.banner_id AND banner_slider.slider_id=' . $id,
            ['position']
        );

        $collection->addOrder('position', 'ASC');

        return $collection;
    }

    /**
     * @return Collection
     */
    public function getActiveSliders()
    {
        /** @var Collection $collection */
        $collection = $this->sliderFactory->create()
            ->getCollection()
            ->addFieldToFilter('customer_group_ids', ['finset' => $this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_GROUP)])
            ->addFieldToFilter('status', 1)
            ->addOrder('priority');

        $collection->getSelect()
            ->where('FIND_IN_SET(0, store_ids) OR FIND_IN_SET(?, store_ids)', $this->storeManager->getStore()->getId())
            ->where('FIND_IN_SET(0, allowed_countries) OR FIND_IN_SET(?, allowed_countries)', $this->geoIpService->getCountry())
            ->where('from_date is null OR from_date <= ?', $this->date->date())
            ->where('to_date is null OR to_date >= ?', $this->date->date());
       
        return $collection;
    }
}
