<?php
/**
 * Athene2 - Advanced Learning Resources Manager
 *
 * @author    Aeneas Rekkas (aeneas.rekkas@serlo.org)
 * @license   LGPL-3.0
 * @license   http://opensource.org/licenses/LGPL-3.0 The GNU Lesser General Public License, version 3.0
 * @link      https://github.com/serlo-org/athene2 for the canonical source repository
 */
namespace Discussion\Controller;

use Discussion\Exception\CommentNotFoundException;
use Discussion\Form\CommentForm;
use Discussion\Form\DiscussionForm;
use Instance\Manager\InstanceManagerAwareTrait;
use Taxonomy\Entity\TaxonomyTermInterface;
use Taxonomy\Manager\TaxonomyManagerInterface;
use User\Manager\UserManagerAwareTrait;
use Uuid\Entity\UuidInterface;
use Uuid\Manager\UuidManagerAwareTrait;
use Zend\View\Model\ViewModel;
use Discussion\Exception\RuntimeException;

class DiscussionController extends AbstractController
{
    use InstanceManagerAwareTrait, UserManagerAwareTrait;

    /**
     * @var \Discussion\Form\CommentForm
     */
    protected $commentForm;

    /**
     * @var \Discussion\Form\DiscussionForm
     */
    protected $discussionForm;

    /**
     * @var TaxonomyManagerInterface
     */
    protected $taxonomyManager;

    public function __construct(
        CommentForm $commentForm,
        DiscussionForm $discussionForm,
        TaxonomyManagerInterface $taxonomyManager
    ) {
        $this->commentForm     = $commentForm;
        $this->discussionForm  = $discussionForm;
        $this->taxonomyManager = $taxonomyManager;
    }

    public function archiveAction()
    {
        $discussion = $this->getDiscussion($this->params('comment'));

        if (!$discussion) {
            return false;
        }

        $this->assertGranted('discussion.archive', $discussion);
        $this->getDiscussionManager()->toggleArchived($this->params('comment'));
        $this->getDiscussionManager()->flush();
        return $this->redirect()->toReferer();
    }

    public function commentAction()
    {
        $discussion = $this->getDiscussion($this->params('discussion'));
        $url        = $this->url()->fromRoute('uuid/get', ['uuid' => $this->params('discussion')]);
        $ref        = $this->referer()->fromStorage($url, 'discussion-comment');

        if (!$discussion) {
            return false;
        }

        $form = $this->commentForm;
        $this->assertGranted('discussion.comment.create', $discussion);

        if ($this->getRequest()->isPost()) {
            $data = [
                'instance' => $this->getInstanceManager()->getInstanceFromRequest(),
                'parent'   => $this->params('discussion'),
                'author'   => $this->getUserManager()->getUserFromAuthenticator()
            ];
            $form->setData(array_merge($this->params()->fromPost(), $data));
            if ($form->isValid()) {
                $this->getDiscussionManager()->commentDiscussion($form);
                $this->getDiscussionManager()->flush();
                $this->flashMessenger()->addSuccessMessage('Your comment has been saved.');
                return $this->redirect()->toUrl($ref);
            }
        } else {
            $this->referer()->store('discussion-comment');
        }

        $view = new ViewModel(['form' => $form, 'discussion' => $discussion, 'ref' => $ref]);
        $view->setTemplate('discussion/discussion/comment');
        return $view;
    }

    public function showAction()
    {
        $discussion = $this->getDiscussion();

        if (!$discussion) {
            return false;
        }

        $view = new ViewModel([
            'discussion' => $discussion,
            'user'       => $this->getUserManager()->getUserFromAuthenticator()
        ]);
        $view->setTemplate('discussion/discussion/index');

        return $view;
    }

    public function startAction()
    {
        $form     = $this->getForm('discussion', $this->params('on'));
        $instance = $this->getInstanceManager()->getInstanceFromRequest();
        $author   = $this->getUserManager()->getUserFromAuthenticator();
        $url      = $this->url()->fromRoute('uuid/get', ['uuid' => $this->params('on')]);
        $ref      = $this->referer()->fromStorage($url);
        $view     = new ViewModel(['form' => $form, 'ref' => $ref]);
        $this->assertGranted('discussion.create', $instance);

        if ($this->getRequest()->isPost()) {
            $data = [
                'instance' => $instance,
                'author'   => $author,
                'object'   => $this->params('on')
            ];
            $form->setData(array_merge($this->params()->fromPost(), $data));
            if ($form->isValid()) {
                $this->getDiscussionManager()->startDiscussion($form);
                $this->getDiscussionManager()->flush();
                if (!$this->getRequest()->isXmlHttpRequest()) {
                    $this->flashMessenger()->addSuccessMessage('Your discussion has been started.');
                    return $this->redirect()->toUrl($ref, 'discussion-start');
                }
                $view->setTerminal(true);
                return $view;
            }
        } else {
            $this->referer()->store('discussion-start');
        }

        $view->setTemplate('discussion/discussion/start');
        //$this->layout('editor/layout');

        return $view;
    }

    public function voteAction()
    {
        $discussion = $this->getDiscussion($this->params('comment'));

        if (!$discussion) {
            return false;
        }

        $user = $this->getUserManager()->getUserFromAuthenticator();
        $this->assertGranted('discussion.vote', $discussion);

        if ($this->params('vote') == 'down') {
            if ($discussion->downVote($user)) {
                $this->flashMessenger()->addSuccessMessage('You have downvoted this comment.');
            } else {
                $this->flashMessenger()->addErrorMessage('You can\'t downvote this comment.');
            }
        } else {
            if ($discussion->upVote($user)) {
                $this->flashMessenger()->addSuccessMessage('You have upvoted this comment.');
            } else {
                $this->flashMessenger()->addErrorMessage('You can\'t upvote this comment.');
            }
        }

        $this->getDiscussionManager()->flush();
        return $this->redirect()->toReferer();
    }

    protected function getDiscussion($id = null)
    {
        $id = $id ? : $this->params('id');
        try {
            return $this->getDiscussionManager()->getComment($id);
        } catch (CommentNotFoundException $e) {
            $this->getResponse()->setStatusCode(404);
            return null;
        }
    }

    protected function getForm($type, $id)
    {
        switch ($type) {
            case 'discussion':
                $form = clone $this->discussionForm;
                return $form;
                break;
            case 'comment':
                $form = clone $this->commentForm;
                $form->setAttribute(
                    'action',
                    $this->url()->fromRoute(
                        'discussion/discussion/comment',
                        ['discussion' => $id]
                    )
                );
                return $form;
                break;
            default:
                throw new RuntimeException();
        }
    }
}
