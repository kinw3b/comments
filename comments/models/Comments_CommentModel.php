<?php
namespace Craft;

class Comments_CommentModel extends BaseElementModel
{
    // Properties
    // =========================================================================

    protected $elementType = 'Comments_Comment';

	const APPROVED	= 'approved';
	const PENDING	= 'pending';
	const SPAM		= 'spam';
	const TRASHED	= 'trashed';


    // Public Methods
    // =========================================================================

    public function isEditable()
    {
        return false;
    }

    public function hasTitles()
    {
        return false;
    }

    public function isLocalized()
    {
        return false;
    }

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('comments/edit/' . $this->id);
    }

    public function validate($attributes = null, $clearErrors = true)
    {
        $settings = craft()->comments->getSettings();

        // Let's check for spam!
        if (!craft()->comments_protect->verifyFields()) {
            $this->addError('comment', Craft::t('Form validation failed. Marked as spam.'));
        }

        // Check against any security keywords we've set. Can be words, IP's, User Agents, etc.
        if (!craft()->comments_security->checkSecurityPolicy($this)) {
            $this->addError('comment', Craft::t('Comment blocked due to security policy.'));
        }

        // Protect against Anonymous submissions, if turned off
        if (!$settings->allowAnonymous && !$this->userId) {
            $this->addError('comment', Craft::t('Must be logged in to comment.'));

            // Additionally, check for user email/name, which is compulsary for guests
            if (!$this->name) {
                $this->addError('name', Craft::t('Name is required.'));
            }

            if (!$this->email) {
                $this->addError('email', Craft::t('Email is required.'));
            }
        }

        // Is someone sneakily making a comment on a non-allowed element through some black magic POST-ing?
        $element = craft()->elements->getElementById($this->elementId);
        if (!craft()->comments_settings->checkPermissions($element)) {
            $this->addError('comment', Craft::t('Comments are disabled for this element.'));
        }

        // Must have an actual comment
        if (!$this->comment) {
            $this->addError('comment', Craft::t('Comment must not be blank.'));
        }

        return parent::validate($attributes, false);
    }

    public function getExcerpt($startPos=0, $maxLength=100) {
        if (strlen($this->comment) > $maxLength) {
            $excerpt   = substr($this->comment, $startPos, $maxLength-3);
            $lastSpace = strrpos($excerpt, ' ');
            $excerpt   = substr($excerpt, 0, $lastSpace);
            $excerpt  .= '...';
        } else {
            $excerpt = $this->comment;
        }
        
        return $excerpt;
    }

    public function deleteActionUrl($options = array())
    {
        return CommentsHelper::deleteAction($this, $options);
    }

    public function isClosed($options = array())
    {
        return craft()->comments_settings->checkClosed($this);
    }

    //
    // Flags
    //

    public function flagActionUrl($options = array())
    {
        return CommentsHelper::flagAction($this, $options);
    }

    public function isFlagged($options = array())
    {
        return craft()->comments_flag->isOverFlagThreshold($this);
    }

    public function flags($options = array())
    {
        return craft()->comments_flag->getFlagsByCommentId($this->id);
    }

    //
    // Votes
    //

    public function upvoteActionUrl($options = array())
    {
        return CommentsHelper::upvoteAction($this, $options);
    }

    public function downvoteActionUrl($options = array())
    {
        return CommentsHelper::downvoteAction($this, $options);
    }

    public function votes($options = array())
    {
        return craft()->comments_vote->getVotesByCommentId($this->id);
    }

    public function voteCount($options = array())
    {
        $downvotes = craft()->comments_vote->getDownvotesByCommentId($this->id);
        $upvotes = craft()->comments_vote->getUpvotesByCommentId($this->id);

        return count($upvotes) - count($downvotes);
    }

    public function canVote($options = array())
    {
        $user = craft()->userSession->getUser();

        // Only registered users can vote
        if (!$user) {
            return false;
        }

        // User cannot vote on their own comment
        if ($user->id == $this->userId) {
            return false;
        }

        return true;
    }

    public function canUpVote($options = array())
    {
        $user = craft()->userSession->getUser();

        // User can only upvote once
        if ($user) {
            if (craft()->comments_vote->hasUpVoted($this, $user)) {
                return false;
            }
        }

        return $this->canVote();
    }

    public function canDownVote($options = array())
    {
        $user = craft()->userSession->getUser();

        // User can only upvote once
        if ($user) {
            if (craft()->comments_vote->hasDownVoted($this, $user)) {
                return false;
            }
        }

        return $this->canVote();
    }

    public function isPoorlyRated($options = array())
    {
        return craft()->comments_vote->isOverDownvoteThreshold($this);
    }
    


    // Protected Methods
    // =========================================================================

	protected function defineAttributes()
	{
        return array_merge(parent::defineAttributes(), array(
			'id'			=> array(AttributeType::Number),
			'elementId'		=> array(AttributeType::Number),
            'elementType'   => array(AttributeType::String),
            'userId'		=> array(AttributeType::Number),
            'structureId'   => array(AttributeType::Number),
			'status'		=> array(AttributeType::Enum, 'values' => array(
			    Comments_CommentModel::APPROVED,
			    Comments_CommentModel::PENDING,
			    Comments_CommentModel::SPAM,
			    Comments_CommentModel::TRASHED,
			)),
			'name'			=> array(AttributeType::String),
			'email'			=> array(AttributeType::Email),
			'url'			=> array(AttributeType::Url),
			'ipAddress'		=> array(AttributeType::String),
			'userAgent'		=> array(AttributeType::String),
			'comment'		=> array(AttributeType::String),

			// Just used for saving
			'parentId'      => AttributeType::Number,
        ));
	}
}