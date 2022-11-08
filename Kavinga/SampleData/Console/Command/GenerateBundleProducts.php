<?php

namespace Kavinga\SampleData\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GenerateBundleProducts extends Command
{
    private \Magento\Catalog\Model\ProductFactory $productFactory;
    private \Magento\Framework\App\State $state;
    private \Magento\Bundle\Api\Data\OptionInterfaceFactory $optionInterfaceFactory;
    private \Magento\Bundle\Api\Data\LinkInterfaceFactory $linkInterfaceFactory;
    private \Magento\Catalog\Api\ProductRepositoryInterface $productRepository;

    public function __construct(
        \Magento\Catalog\Model\ProductFactory           $productFactory,
        \Magento\Framework\App\State                    $state,
        \Magento\Bundle\Api\Data\OptionInterfaceFactory $optionInterfaceFactory,
        \Magento\Bundle\Api\Data\LinkInterfaceFactory   $linkInterfaceFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        parent::__construct();
        $this->productFactory = $productFactory;
        $this->state = $state;
        $this->optionInterfaceFactory = $optionInterfaceFactory;
        $this->linkInterfaceFactory = $linkInterfaceFactory;
        $this->productRepository = $productRepository;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('kavinga:sampledata:generate-bundle')
            ->setDescription('Generates Bundle Sample data');
    }

    private function createSimpleProduct(InputInterface $input, OutputInterface $output)
    {
        for ($x = 0; $x < 220; $x++) {
            $simpleProduct = $this->productFactory->create();
            $simpleProduct->setData([
                "name" => "simple - " . $x,
                "sku" => "simple - " . $x,
                "attribute_set_id" => 4,
                "status" => 1,
                "weight" => 1,
                "visibility" => 4,
                "type_id" => "simple",
                "price" => 10,
                "stock_data" => [
                    'is_in_stock' => 1,
                    'qty' => 100
                ]
            ]);
            $output->writeln("creating product - " . $simpleProduct->getSku());
            $this->productRepository->save($simpleProduct);
        }
    }

    private function createBundleProduct(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("creating bundle product");
        $bundleProduct = $this->productFactory->create();

        $bundleProduct->setData([
            "name" => "bundle - 1",
            "sku" => "bundle - 1",
            "attribute_set_id" => 4,
            "status" => 1,
            "weight" => 1,
            "visibility" => 4,
            "type_id" => "bundle",
            "sku_type" => 1,
            "shipment_type" => 0,
            "price_view" => 0,
            "stock_data" => [
                'is_in_stock' => 1
            ]
        ]);
        $bundleProduct = $this->productRepository->save($bundleProduct);
        $optionsArray = [];
        $bundleSelectionDataArray = [];
        $noOfOptions = 22;
        $noOfSelectionsPerOption = 5;
        for ($x = 0; $x < $noOfOptions; $x++) {
            $optionsArray[] = [
                "title" => "Bundle option - " . $x,
                'default_title' => "Bundle option - " . $x,
                'type' => 'select',
                'required' => 1,
                'delete' => '',
            ];
            // bundle selection data
            $bundleSelectionArray = [];
            $skuIncrement = $x * $noOfSelectionsPerOption;
            for ($y = 0; $y < $noOfSelectionsPerOption; $y++) {
                $bundleSelectionArray[] = [
                    'sku' => 'simple - ' . $y + $skuIncrement,
                    'selection_qty' => 1,
                    'selection_can_change_qty' => 1,
                    'delete' => ''
                ];
            }
            $bundleSelectionDataArray[] = $bundleSelectionArray;
        }

        $bundleProduct->setBundleOptionsData($optionsArray);
        $bundleProduct->setBundleSelectionsData($bundleSelectionDataArray);

        // setBundleSelectionsData set your Product Id
        if ($bundleProduct->getBundleOptionsData()) {
            $options = [];
            foreach ($bundleProduct->getBundleOptionsData() as $key => $optionData) {
                if (!(bool)$optionData['delete']) {
                    $option = $this->optionInterfaceFactory->create();
                    $option->setData($optionData);
                    $option->setSku($bundleProduct->getSku());
                    $option->setOptionId(null);
                    $linksArray = [];
                    $bundleLinkData = $bundleProduct->getBundleSelectionsData();
                    if (!empty($bundleLinkData[$key])) {
                        foreach ($bundleLinkData[$key] as $linkData) {
                            if (!(bool)$linkData['delete']) {
                                $link = $this->linkInterfaceFactory->create();
                                $link->setData($linkData);
                                $linkProduct = $this->productRepository->get($linkData['sku']);
                                $link->setSku($linkProduct->getSku());
                                $link->setQty($linkData['selection_qty']);
                                if (isset($linkdata['selection_can_change_qty'])) {
                                    $link->setCanChangeQuantity($linkData['selection_can_change_qty']);
                                }
                                $linksArray[] = $link;
                            }
                        }
                        $option->setProductLinks($linksArray);
                        $options[] = $option;
                    }
                }
            }
            $extensionAttribute = $bundleProduct->getExtensionAttributes();
            $extensionAttribute->setBundleProductOptions($options);
            $bundleProduct->setExtensionAttributes($extensionAttribute);
        }
        $this->productRepository->save($bundleProduct);
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode("adminhtml");
        $this->createSimpleProduct($input, $output);
        $this->createBundleProduct($input, $output);
        return 1;
    }
}
