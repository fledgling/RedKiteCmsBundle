<?php
/**
 * This file is part of the RedKiteCmsBunde Application and it is distributed
 * under the GPL LICENSE Version 2.0. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) RedKite Labs <webmaster@redkite-labs.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.redkite-labs.com
 *
 * @license    GPL LICENSE Version 2.0
 *
 */

namespace RedKiteLabs\RedKiteCmsBundle\Core\Content\Template;

use RedKiteLabs\RedKiteCmsBundle\Core\EventsHandler\AlEventsHandlerInterface;
use RedKiteLabs\RedKiteCmsBundle\Core\Content\Validator\AlParametersValidatorInterface;
use RedKiteLabs\RedKiteCmsBundle\Core\Content\PageBlocks\AlPageBlocksInterface;
use RedKiteLabs\RedKiteCmsBundle\Core\Repository\Factory\AlFactoryRepositoryInterface;
use RedKiteLabs\RedKiteCmsBundle\Core\Content\Block\AlBlockManagerFactoryInterface;
use RedKiteLabs\RedKiteCmsBundle\Core\Content\Slot\AlSlotManager;
use RedKiteLabs\ThemeEngineBundle\Core\ThemeSlots\AlSlot;
use RedKiteLabs\ThemeEngineBundle\Core\Template\AlTemplate;
use RedKiteLabs\RedKiteCmsBundle\Core\Repository\Repository\BlockRepositoryInterface;
use RedKiteLabs\RedKiteCmsBundle\Core\Content\Block\AlBlockManagerFactory;
use RedKiteLabs\RedKiteCmsBundle\Core\Event\Content;
use RedKiteLabs\RedKiteCmsBundle\Core\Exception\Content\General\InvalidArgumentTypeException;
use RedKiteLabs\ThemeEngineBundle\Core\ThemeSlots\AlThemeSlotsInterface;

/**
 * AlTemplateManager wrap an AlTemplate object to manage the template's slots when
 * RedKiteCms editor is active
 * *
 * @see AlSlotManager
 * @author RedKite Labs <webmaster@redkite-labs.com>
 *
 * @api
 */
class AlTemplateManager extends AlTemplateBase
{
    protected $slotManagers = array();
    protected $template;
    protected $blockRepository;
    protected $pageBlocks;
    protected $themeSlots;
    protected $dispatcher;

    /**
     * Constructor
     *
     * @param \RedKiteLabs\RedKiteCmsBundle\Core\EventsHandler\AlEventsHandlerInterface           $eventsHandler
     * @param \RedKiteLabs\RedKiteCmsBundle\Core\Repository\Factory\AlFactoryRepositoryInterface  $factoryRepository
     * @param \RedKiteLabs\RedKiteCmsBundle\Core\Content\Block\AlBlockManagerFactoryInterface     $blockManagerFactory
     * @param \RedKiteLabs\RedKiteCmsBundle\Core\Content\Validator\AlParametersValidatorInterface $validator
     */
    public function __construct(AlEventsHandlerInterface $eventsHandler, AlFactoryRepositoryInterface $factoryRepository, AlBlockManagerFactoryInterface $blockManagerFactory = null, AlParametersValidatorInterface $validator = null)
    {
        $blockManagerFactory = (null === $blockManagerFactory) ? new AlBlockManagerFactory($eventsHandler) : $blockManagerFactory;
        parent::__construct($eventsHandler, $blockManagerFactory, $validator);

        $this->factoryRepository = $factoryRepository;
        $this->blockRepository = $this->factoryRepository->createRepository('Block');
        $this->dispatcher = $eventsHandler->getEventDispatcher();
    }

    /**
     * Clones the holden objects, when the object is cloned
     *
     * @api
     */
    public function __clone()
    {
        if (null !== $this->template) {
            $this->template = clone($this->template);
        }

        if (null !== $this->pageBlocks) {
            $this->pageBlocks = clone($this->pageBlocks);
        }

        if (null !== $this->themeSlots) {
            $this->themeSlots = clone($this->themeSlots);
        }

        if (null !== $this->blockRepository) {
            $this->blockRepository = clone($this->blockRepository);
        }
    }

    /**
     * Returns the current AlTemplateobject
     *
     * @return \RedKiteLabs\ThemeEngineBundle\Core\Template\AlTemplate
     *
     * @api
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Returns the current AlThemeSlots object
     *
     * @return \RedKiteLabs\ThemeEngineBundle\Core\ThemeSlots\AlThemeSlots
     *
     * @api
     */
    public function getThemeSlots()
    {
        return $this->themeSlots;
    }

    /**
     * Returns the current page contents container object
     *
     * @return \RedKiteLabs\RedKiteCmsBundle\Core\Content\PageBlocks\AlPageBlocks
     *
     * @api
     */
    public function getPageBlocks()
    {
        return $this->pageBlocks;
    }

    /**
     * Sets the block model interface
     *
     * @param  \RedKiteLabs\RedKiteCmsBundle\Core\Repository\Repository\BlockRepositoryInterface $v
     * @return \RedKiteLabs\RedKiteCmsBundle\Core\Content\Template\AlTemplateManager
     *
     * @api
     */
    public function setBlockRepository(BlockRepositoryInterface $v)
    {
        $this->blockRepository = $v;

        return $this;
    }

    /**
     * Sets the block model object associated to the template manager
     *
     * @return BlockRepositoryInterface
     *
     * @api
     */
    public function getBlockRepository()
    {
        return $this->blockRepository;
    }

    /**
     * Returns the managed slot managers
     * @param  boolean $removeIncludedSlots
     * @return type
     *
     * @api
     */
    public function getSlotManagers($removeIncludedSlots = false)
    {
        return ( ! $removeIncludedSlots) ? $this->slotManagers : array_intersect_key($this->slotManagers, array_flip(array_keys($this->themeSlots->getSlots())));
    }

    /**
     * Returns the slot manager that matches the given parameter
     *
     * @param  string     $slotName
     * @return null|array
     *
     * @api
     */
    public function getSlotManager($slotName)
    {
        if ( ! is_string($slotName)) {
            return null;
        }

        return (array_key_exists($slotName, $this->slotManagers)) ? $this->slotManagers[$slotName] : null;
    }

    /**
     * Returns the slot manager as an array
     *
     * @param  string                    $slotName
     * @return array
     * @throws \InvalidArgumentException
     *
     * @api
     */
    public function slotToArray($slotName)
    {
        if (!is_string($slotName)) {
            throw new InvalidArgumentTypeException('exception_slotToArray_accepts_only_strings');
        }

        if (!array_key_exists($slotName, $this->slotManagers)) {
            return array();
        }

        $slotManager = $this->slotManagers[$slotName];

        return $slotManager->getBlockManagersCollection()->toArray();
    }

    /**
     * Converts slotManagers to an array
     *
     * @return array
     *
     * @api
     */
    public function slotsToArray()
    {
        $slotContents = array();
        foreach ($this->slotManagers as $slotName => $slot) {
            $slotContents[$slotName] = $slot->getBlockManagersCollection()->toArray();
        }

        return $slotContents;
    }

    /**
     * Refreshes the TemplateManager
     *
     * @param  \RedKiteLabs\ThemeEngineBundle\Core\ThemeSlots\AlThemeSlotsInterface        $themeSlots
     * @param  \RedKiteLabs\ThemeEngineBundle\Core\Template\AlTemplate                     $template
     * @param  \RedKiteLabs\RedKiteCmsBundle\Core\Content\PageBlocks\AlPageBlocksInterface $pageBlocks
     * @return \RedKiteLabs\RedKiteCmsBundle\Core\Content\Template\AlTemplateManager
     */
    public function refresh(AlThemeSlotsInterface $themeSlots, AlTemplate $template = null, AlPageBlocksInterface $pageBlocks = null)
    {
        $this->themeSlots = $themeSlots;
        $this->template = $template;
        $this->pageBlocks = $pageBlocks;

        $this->setUpSlotManagers();

        return $this;
    }

    /**
     * Populates each slot using the default contents and saves them to the database.
     *
     * This method is used to add a new page based on the template managed by this object. The slots
     * are filled up using the dafault values provided by each single slot.
     *
     *
     * @param  int                                                           $idLanguage   The id that identified the language to add
     * @param  int                                                           $idPage       The id that identified the page to add
     * @param  boolean                                                       $skipRepeated True skips the slots that are repeated on page
     * @return boolean
     * @throws \RedKiteLabs\RedKiteCmsBundle\Core\Content\Template\Exception
     *
     * @api
     */
    public function populate($idLanguage, $idPage, $skipRepeated = false)
    {
        try {
            $this->dispatcher->dispatch(Content\TemplateManagerEvents::BEFORE_POPULATE, new Content\TemplateManager\BeforePopulateEvent($this));

            $result = false;
            $this->blockRepository->startTransaction();
            foreach ($this->slotManagers as $slotManager) {

                if ($skipRepeated && ($this->isIncluded($slotManager->getSlotName()) || $slotManager->getRepeated() != 'page')) {
                    continue;
                }

                $slotManager
                    ->setForceSlotAttributes(true)
                    ->setSkipSiteLevelBlocks(true)
                ;

                $result = $slotManager->addBlock(
                    array(
                        "idLanguage" => $idLanguage,
                        "idPage" => $idPage,
                    )
                );

                if (false === $result) {
                    break;
                }
            }

            $this->dispatcher->dispatch(Content\TemplateManagerEvents::BEFORE_POPULATE_COMMIT, new Content\TemplateManager\BeforePopulateCommitEvent($this));

            if ($result !== false) {
                $this->blockRepository->commit();
            } else {
                $this->blockRepository->rollBack();
            }
            $this->dispatcher->dispatch(Content\TemplateManagerEvents::AFTER_POPULATE, new Content\TemplateManager\AfterPopulateEvent($this));

            return $result;
        } catch (\Exception $e) {
            $this->blockRepository->rollBack();

            throw $e;
        }
    }

    /**
     * Removes the blocks from the whole slot managers managed by the template manager
     *
     * @param  boolean                                                       $skipRepeated When true skips the slots with a repeated status
     * @return boolean
     * @throws \RedKiteLabs\RedKiteCmsBundle\Core\Content\Template\Exception
     *
     * @api
     */
    public function clearBlocks($skipRepeated = true)
    {
        try {
            $result = null;
            $this->dispatcher->dispatch(Content\TemplateManagerEvents::BEFORE_CLEAR_BLOCKS, new Content\TemplateManager\BeforeClearBlocksEvent($this));

            $this->blockRepository->startTransaction();
            foreach ($this->slotManagers as $slotManager) {
                if ($skipRepeated && $slotManager->getSlot()->getRepeated() != 'page') {
                    continue;
                }
                $result = $slotManager->deleteBlocks();

                if (false === $result) {
                    break;
                }
            }

            $this->dispatcher->dispatch(Content\TemplateManagerEvents::BEFORE_CLEAR_BLOCKS_COMMIT, new Content\TemplateManager\BeforeClearBlocksCommitEvent($this));

            if ($result !== false) {
                $this->blockRepository->commit();
            } else {
                $this->blockRepository->rollBack();
            }

            $this->dispatcher->dispatch(Content\TemplateManagerEvents::AFTER_CLEAR_BLOCKS, new Content\TemplateManager\AfterClearBlocksEvent($this));

            return $result;
        } catch (\Exception $e) {
            $this->blockRepository->rollBack();

            throw $e;
        }
    }

    /**
     * Clear the blocks from the whole slot managers managed by the template manager,
     * for a page identified by the required parameters
     *
     * @param  int                                                           $languageId
     * @param  int                                                           $pageId
     * @param  boolean                                                       $skipRepeated
     * @return boolean
     * @throws \RedKiteLabs\RedKiteCmsBundle\Core\Content\Template\Exception
     *
     * @api
     */
    public function clearPageBlocks($languageId, $pageId, $skipRepeated = true)
    {
        try {
            $this->blockRepository->startTransaction();

            $pageBlocks = clone($this->pageBlocks);
            $this->pageBlocks->refresh($languageId, $pageId);

            $result = $this->clearBlocks($skipRepeated);
            $this->pageBlocks = $pageBlocks;

            if ($result !== false) {
                $this->blockRepository->commit();

                return $result;
            }

            $this->blockRepository->rollBack();

            return $result;
        } catch (\Exception $e) {
            $this->blockRepository->rollBack();

            throw $e;
        }
    }

    /**
     * Creates the slot managers from the current template slot class
     *
     * @return null|boolean
     * @throws Exception\EmptyTemplateSlotsException
     */
    protected function setUpSlotManagers()
    {
        if (null === $this->themeSlots || null === $this->template) {
            return;
        }

        $this->slotManagers = array();
        $templateSlots = $this->template->getSlots();
        $themeSlots = $this->themeSlots->getSlots();
        foreach ($themeSlots as $slotName => $slot) {

            // slots passes only when they are repeated or belongs the current template
            if ($slot->getRepeated() == 'page' && ! in_array($slotName, $templateSlots)) {
                continue;
            }

            $this->slotManagers[$slotName] = $this->createSlotManager($slot);
        }

        if (null === $this->pageBlocks) {
            return;
        }

        // Looks for included blocks' slots
        $includedSlots = array_diff(array_keys($this->pageBlocks->getBlocks()), array_keys($themeSlots));
        foreach ($includedSlots as $slotName) {
            if ($slotName != "") {
                $slot = new AlSlot($slotName);
                $this->slotManagers[$slotName] = $this->createSlotManager($slot);
            }
        }
    }

    /**
     * Creates the slot manager for the given slot
     *
     * @param  \RedKiteLabs\ThemeEngineBundle\Core\ThemeSlots\AlSlot         $slot
     * @return \RedKiteLabs\RedKiteCmsBundle\Core\Content\Slot\AlSlotManager
     */
    protected function createSlotManager(AlSlot $slot)
    {
        $slotName = $slot->getSlotName();
        $blocks = array();
        if (null !== $this->pageBlocks) {
            $blocks = $this->pageBlocks->getSlotBlocks($slotName);
        }
        $slotManager = new AlSlotManager($slot, $this->blockRepository, $this->blockManagerFactory);
        $slotManager->setUpBlockManagers($blocks);

        return $slotManager;
    }

    /**
     * Verifies when the block is included
     *
     * @param  string  $slotName
     * @return boolean
     */
    private function isIncluded($slotName)
    {
        if ( ! preg_match('/^([0-9]+)\-/', $slotName, $matches)) {
            return false;
        }

        // @codeCoverageIgnoreStart
        $blockId = $matches[1];
        $slotBlocks = $this->pageBlocks->getBlocks();
        foreach ($slotBlocks as $blocks) {
            foreach ($blocks as $block) {
                if ($block->getId() == $blockId) {
                    return true;
                }
            }
        }

        return false;
        // @codeCoverageIgnoreEnd
    }
}
