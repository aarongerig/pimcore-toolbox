<?php

namespace ToolboxBundle\Manager;

use Pimcore\Extension\Document\Areabrick\AbstractTemplateAreabrick;
use Pimcore\Extension\Document\Areabrick\AreabrickManager;

class AreaManager implements AreaManagerInterface
{
    public ConfigManagerInterface $configManager;
    public AreabrickManager $brickManager;
    public PermissionManagerInterface $permissionManager;

    public function __construct(
        ConfigManagerInterface $configManager,
        AreabrickManager $brickManager,
        PermissionManagerInterface $permissionManager
    ) {
        $this->configManager = $configManager;
        $this->brickManager = $brickManager;
        $this->permissionManager = $permissionManager;
    }

    public function getAreaBlockName(?string $type = null): string
    {
        if ($type === 'parallaxContainerSection') {
            return 'Parallax Container Section';
        }

        return $this->brickManager->getBrick($type)->getName();
    }

    public function getAreaBlockConfiguration(?string $type, bool $fromSnippet = false, bool $editMode = false): array
    {
        if ($fromSnippet === true) {
            $availableBricks = $this->getAvailableBricksForSnippets($type);
        } else {
            $availableBricks = $this->getAvailableBricks($type);
        }

        $areaBlockConfiguration = $this->configManager->getConfig('area_block_configuration');
        $areaBlockConfigurationArray = is_null($areaBlockConfiguration) ? [] : $areaBlockConfiguration;

        $configuration = [];

        $configuration['params'] = $availableBricks['params'];
        $configuration['allowed'] = $availableBricks['allowed'];

        $toolboxGroup = [
            [
                'name'     => 'Toolbox',
                'elements' => $this->getToolboxBricks()
            ]
        ];

        if (isset($areaBlockConfigurationArray['groups']) && $areaBlockConfigurationArray['groups'] !== false) {
            $groups = array_merge($toolboxGroup, $areaBlockConfigurationArray['groups']);
        } else {
            $groups = $toolboxGroup;
        }

        $cleanedGroups = [];
        $cleanedGroupsSorted = [];

        foreach ($groups as $groupName => $groupData) {
            $groupName = $groupData['name'];
            $cleanedGroup = [];

            foreach ($groupData['elements'] as $element) {
                if (in_array($element, $availableBricks['allowed'], true)) {
                    $cleanedGroup[] = $element;
                }
            }

            //ok, group elements found, add them
            if (count($cleanedGroup) > 0) {
                $cleanedGroups[$groupName] = $cleanedGroup;
                $cleanedGroupsSorted = array_merge($cleanedGroupsSorted, $cleanedGroup);
                //sort group by cleaned group
                sort($cleanedGroupsSorted);
            }
        }

        if (count($cleanedGroups) > 0) {
            $configuration['sorting'] = $cleanedGroupsSorted;
            $configuration['group'] = $cleanedGroups;
        }

        $configuration['controlsAlign'] = $areaBlockConfigurationArray['controlsAlign'];
        $configuration['controlsTrigger'] = $areaBlockConfigurationArray['controlsTrigger'];
        $configuration['areablock_toolbar'] = $areaBlockConfigurationArray['toolbar'];

        $configuration['toolbox_permissions'] = [
            'disallowed' => $editMode ? $this->permissionManager->getDisallowedEditables($configuration['allowed']) : []
        ];

        return $configuration;
    }

    /**
     * @throws \Exception
     */
    private function getActiveBricks(bool $arrayKeys = true): array
    {
        $areaElements = $this->brickManager->getBricks();

        //sort area elements by key => area name
        ksort($areaElements);

        /** @var AbstractTemplateAreabrick $areaElementData */
        foreach ($areaElements as $areaElementName => $areaElementData) {
            if (!$this->brickManager->isEnabled($areaElementName)) {
                unset($areaElements[$areaElementName]);
            }
        }

        //if in context, check if areas are available in given context
        if ($this->configManager->isContextConfig()) {
            $contextConfiguration = $this->configManager->getCurrentContextSettings();

            if ($contextConfiguration['merge_with_root'] === true) {
                if (!empty($contextConfiguration['enabled_areas'])) {
                    foreach ($areaElements as $areaElementName => $areaElementData) {
                        if (!in_array($areaElementName, $contextConfiguration['enabled_areas'], true)) {
                            unset($areaElements[$areaElementName]);
                        }
                    }
                } elseif (!empty($contextConfiguration['disabled_areas'])) {
                    foreach ($areaElements as $areaElementName => $areaElementData) {
                        if (in_array($areaElementName, $contextConfiguration['disabled_areas'], true)) {
                            unset($areaElements[$areaElementName]);
                        }
                    }
                }
            } else {
                foreach ($areaElements as $areaElementName => $areaElementData) {
                    $coreAreas = $this->configManager->getConfig('areas');
                    $customAreas = $this->configManager->getConfig('custom_areas');
                    if (!array_key_exists($areaElementName, $coreAreas) &&
                        !array_key_exists($areaElementName, $customAreas)) {
                        unset($areaElements[$areaElementName]);
                    }
                }
            }
        }

        if ($arrayKeys === true) {
            return array_keys($areaElements);
        }

        return $areaElements;
    }

    /**
     * @throws \Exception
     */
    private function getAvailableBricks(string $type): array
    {
        $areaElements = $this->getActiveBricks();

        $areaAppearance = $this->configManager->getConfig('areas_appearance');
        $elementAllowed = isset($areaAppearance[$type]) ? $areaAppearance[$type]['allowed'] : [];
        $elementDisallowed = isset($areaAppearance[$type]) ? $areaAppearance[$type]['disallowed'] : [];

        // strict fill means: only add defined elements.
        $strictFill = !empty($elementAllowed);

        $bricks = [];
        foreach ($areaElements as $a) {
            // allowed rule comes first!
            if ($strictFill === true) {
                if (in_array($a, $elementAllowed, true)) {
                    $bricks[] = $a;
                }
            } else {
                if (!in_array($a, $elementDisallowed, true)) {
                    $bricks[] = $a;
                }
            }
        }

        return ['allowed' => $bricks, 'params' => []];
    }

    /**
     * @throws \Exception
     */
    private function getAvailableBricksForSnippets(string $type): array
    {
        $areaElements = $this->getActiveBricks();

        $areaAppearance = $this->configManager->getConfig('snippet_areas_appearance');
        $elementAllowed = isset($areaAppearance[$type]) ? $areaAppearance[$type]['allowed'] : [];
        $elementDisallowed = isset($areaAppearance[$type]) ? $areaAppearance[$type]['disallowed'] : [];

        $bricks = [];
        foreach ($areaElements as $a) {
            // allowed rule comes first!
            if (!empty($elementAllowed)) {
                if (in_array($a, $elementAllowed, true)) {
                    $bricks[] = $a;
                }
            } else {
                if (!in_array($a, $elementDisallowed, true)) {
                    $bricks[] = $a;
                }
            }
        }

        return ['allowed' => $bricks, 'params' => []];
    }

    /**
     * @throws \Exception
     */
    private function getToolboxBricks(): array
    {
        $areaElements = $this->getActiveBricks(false);
        $toolboxBricks = [];

        /** @var AbstractTemplateAreabrick $areaElementData */
        foreach ($areaElements as $areaElementName => $areaElementData) {
            if (str_starts_with($areaElementData->getDescription(), 'Toolbox')) {
                $toolboxBricks[$areaElementName] = $areaElementData;
            }
        }

        if (isset($toolboxBricks['content'])) {
            $toolboxBricks = ['content' => $toolboxBricks['content']] + $toolboxBricks;
        }

        if (isset($toolboxBricks['headline'])) {
            $toolboxBricks = ['headline' => $toolboxBricks['headline']] + $toolboxBricks;
        }

        return array_keys($toolboxBricks);
    }
}
