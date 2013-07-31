<?php

/*
 * This file is part of the CCDNForum ForumBundle
 *
 * (c) CCDN (c) CodeConsortium <http://www.codeconsortium.com/>
 *
 * Available on github <http://www.github.com/codeconsortium/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CCDNForum\ForumBundle\Form\Handler\Admin\Board;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Debug\ContainerAwareTraceableEventDispatcher;

use Doctrine\Common\Collections\ArrayCollection;

use CCDNForum\ForumBundle\Component\Dispatcher\ForumEvents;
use CCDNForum\ForumBundle\Component\Dispatcher\Event\AdminBoardEvent;

use CCDNForum\ForumBundle\Entity\Board;

/**
 *
 * @category CCDNForum
 * @package  ForumBundle
 *
 * @author   Reece Fowell <reece@codeconsortium.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @version  Release: 2.0
 * @link     https://github.com/codeconsortium/CCDNForumForumBundle
 *
 */
class BoardDeleteFormHandler
{
    /**
     *
     * @access protected
     * @var \Symfony\Component\Form\FormFactory $factory
     */
    protected $factory;

    /**
     *
     * @access protected
     * @var \CCDNForum\ForumBundle\Form\Type\Admin\Board\BoardDeleteFormType $boardDeleteFormType
     */
    protected $boardDeleteFormType;

    /**
     *
     * @access protected
     * @var \CCDNForum\ForumBundle\Model\Model\BoardModel $boardModel
     */
    protected $boardModel;

    /**
     *
     * @access protected
     * @var \Symfony\Component\Form\Form $form
     */
    protected $form;

    /**
     *
     * @access protected
     * @var \CCDNForum\ForumBundle\Entity\Board $board
     */
    protected $board;

	/**
	 * 
	 * @access protected
	 * @var \Symfony\Component\HttpKernel\Debug\ContainerAwareTraceableEventDispatcher $dispatcher
	 */
	protected $dispatcher;

    /**
     *
     * @access public
     * @param \Symfony\Component\Form\FormFactory                                        $factory
     * @param \CCDNForum\ForumBundle\Form\Type\Admin\Board\BoardDeleteFormType           $boardDeleteFormType
     * @param \CCDNForum\ForumBundle\Model\Model\BoardModel                              $boardModel
     * @param \Symfony\Component\HttpKernel\Debug\ContainerAwareTraceableEventDispatcher $dispatcher
     */
    public function __construct(FormFactory $factory, $boardDeleteFormType, $boardModel, ContainerAwareTraceableEventDispatcher $dispatcher)
    {
        $this->factory = $factory;
        $this->boardDeleteFormType = $boardDeleteFormType;
        $this->boardModel = $boardModel;
		$this->dispatcher = $dispatcher;
    }

	/**
	 * 
	 * @access public
	 * @param \CCDNForum\ForumBundle\Entity\Board $board
	 * @return \CCDNForum\ForumBundle\Form\Handler\Admin\Board\BoardDeleteFormHandler
	 */
	public function setBoard(Board $board)
	{
		$this->board = $board;
		
		return $this;
	}
	
    /**
     *
     * @access public
     * @param  \Symfony\Component\HttpFoundation\Request $request
     * @return bool
     */
    public function process(Request $request)
    {
        $this->getForm();

        if ($request->getMethod() == 'POST') {
            $this->form->bind($request);

            // Validate
            if ($this->form->isValid()) {
                $formData = $this->form->getData();

                if ($this->getSubmitAction($request) == 'post') {
                    $this->onSuccess($formData, $request);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     *
     * @access public
     * @param  \Symfony\Component\HttpFoundation\Request $request
     * @return string
     */
    public function getSubmitAction(Request $request)
    {
        if ($request->request->has('submit')) {
            $action = key($request->request->get('submit'));
        } else {
            $action = 'post';
        }

        return $action;
    }

    /**
     *
     * @access public
     * @return \Symfony\Component\Form\Form
     */
    public function getForm()
    {
        if (null == $this->form) {
			if (!is_object($this->board) && !$this->board instanceof Board) {
				throw new \Exception('Board object must be specified to delete.');
			}
			
            $this->form = $this->factory->create($this->boardDeleteFormType, $this->board);
        }

        return $this->form;
    }

    /**
     *
     * @access protected
     * @param  \CCDNForum\ForumBundle\Entity\Board           $board
     * @param  \Symfony\Component\HttpFoundation\Request     $request
     * @return \CCDNForum\ForumBundle\Model\Model\BoardModel
     */
    protected function onSuccess(Board $board, Request $request)
    {
		$this->dispatcher->dispatch(ForumEvents::ADMIN_BOARD_DELETE_SUCCESS, new AdminBoardEvent($request, $board));
		
		$confirmA = $this->form->get('confirm_delete')->getData();
		$confirmB = $this->form->get('confirm_subordinates')->getData();
		$confirm = array_merge($confirmA, $confirmB);
		
		if (in_array('delete_board', $confirm)) {
			if (! in_array('delete_subordinates', $confirm)) {
				$topics = new ArrayCollection($board->getTopics()->toArray());
				
				$this->boardModel->reassignTopicsToBoard($topics, null)->flush();
			}

	        $this->boardModel->deleteBoard($board)->flush();
		}
		
		return $this->boardModel;
    }
}
