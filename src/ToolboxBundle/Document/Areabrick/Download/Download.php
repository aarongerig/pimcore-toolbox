<?php

namespace ToolboxBundle\Document\Areabrick\Download;

use Doctrine\DBAL\Query\QueryBuilder;
use Pimcore\Model\Document\Editable\Relations;
use Symfony\Component\HttpFoundation\Response;
use ToolboxBundle\Connector\BundleConnector;
use ToolboxBundle\Document\Areabrick\AbstractAreabrick;
use Pimcore\Model\Document\Editable\Area\Info;
use Pimcore\Model\Asset;

class Download extends AbstractAreabrick
{
    public function __construct(protected BundleConnector $bundleConnector)
    {
    }

    public function action(Info $info): ?Response
    {
        parent::action($info);

        /** @var Relations $downloadField */
        $downloadField = $this->getDocumentEditable($info->getDocument(), 'relations', 'downloads');

        $assets = [];
        if (!$downloadField->isEmpty()) {
            /** @var Asset $node */
            foreach ($downloadField->getElements() as $node) {
                if ($node instanceof Asset\Folder) {
                    $assets = array_merge($assets, $this->getByFolder($node));
                } else {
                    $assets = array_merge($assets, $this->getByFile($node));
                }
            }
        }

        $info->setParam('downloads', $assets);

        return null;
    }

    protected function getByFile(Asset $node): array
    {
        if (!$this->bundleConnector->hasBundle('MembersBundle')) {
            return [$node];
        }

        /** @var \MembersBundle\Restriction\ElementRestriction $elementRestriction */
        $elementRestriction = $this->bundleConnector->getBundleService(\MembersBundle\Manager\RestrictionManager::class)->getElementRestrictionStatus($node);
        if ($elementRestriction->getSection() === \MembersBundle\Manager\RestrictionManager::RESTRICTION_SECTION_ALLOWED) {
            return [$node];
        }

        return [];
    }

    protected function getByFolder(Asset\Folder $node): array
    {
        $assetListing = new Asset\Listing();
        $fullPath = rtrim($node->getFullPath(), '/') . '/';
        $assetListing->addConditionParam('path LIKE ?', $fullPath . '%');
        $assetListing->addConditionParam('type != ?', 'folder');

        if ($this->bundleConnector->hasBundle('MembersBundle')) {
            $assetListing->onCreateQueryBuilder(function (QueryBuilder $query) use ($assetListing) {
                $this->bundleConnector
                    ->getBundleService(\MembersBundle\Security\RestrictionQuery::class)
                    ->addRestrictionInjection($query, $assetListing, 'id');
            });
        }

        $assetListing->setOrderKey('filename');
        $assetListing->setOrder('asc');

        return $assetListing->getAssets();
    }

    public function getName(): string
    {
        return 'Downloads';
    }

    public function getDescription(): string
    {
        return 'Toolbox Downloads';
    }
}
