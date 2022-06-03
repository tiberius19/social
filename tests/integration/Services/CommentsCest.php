<?php

namespace Kanvas\Social\Tests\Integration\Social\Service;

use Baka\Support\Random;
use Canvas\Models\SystemModules;
use IntegrationTester;
use Kanvas\Social\Comments;
use Kanvas\Social\Enums\Interactions as EnumsInteractions;
use Kanvas\Social\Interactions;
use Kanvas\Social\Messages as MessagesService;
use Kanvas\Social\MessageTypes;
use Kanvas\Social\Models\MessageComments;
use Kanvas\Social\Models\Messages;
use Kanvas\Social\Models\UsersInteractions;
use Kanvas\Social\Models\UsersReactions;
use Kanvas\Social\Reactions;
use Kanvas\Social\Test\Support\Models\Users;

class CommentsCest
{
    public MessageComments $comment;

    /**
     * Get the first comment.
     *
     * @return void
     */
    protected function getCommentData() : void
    {
        $this->comment = MessageComments::findFirst(['is_deleted = 0', 'order' => 'id ASC']);
    }

    /**
     * Test add comment.
     *
     * @param UnitTester $I
     * @before getCommentData
     *
     * @return void
     */
    public function addComment(IntegrationTester $I) : void
    {
        $user = Users::findFirst(1);

        //Add new SystemModule for Messages
        $systemModule = SystemModules::findFirstOrCreate([
            'conditions' => 'apps_id = :apps_id: and model_name = :model_name: and is_deleted = 0',
            'bind' => [
                'apps_id' => 1,
                'model_name' => 'Kanvas\Social\Models\Messages'
            ]], [
                'name' => 'Messages',
                'slug' => 'messages',
                'apps_id' => 1,
                'model_name' => 'Kanvas\Social\Models\Messages',
                'date' => date('Y-m-d H:i:s')
            ]);

        //Create a new message type
        MessageTypes::create($user, 'comments', 'Test Type');

        $text = [
            'text' => 'Test some messages'
        ];

        //Create a new Message for the comment
        $feed = MessagesService::create($user, 'comments', $text);
        $comment = Comments::add($feed->getId(), 'test-text', $user);
        $this->comment = $comment;
        $I->assertEquals('test-text', $comment->message);
    }

    /**
     * Test comment edit.
     *
     * @param IntegrationTester $I
     * @before getCommentData
     *
     * @return void
     */
    public function editComment(IntegrationTester $I) : void
    {
        $comment = Comments::edit((string) $this->comment->getId(), 'edited-test-text');

        $I->assertEquals('edited-test-text', $comment->message);
    }

    /**
     * Test get Comment.
     *
     * @param IntegrationTester $I
     * @before getCommentData
     *
     * @return void
     */
    public function getComment(IntegrationTester $I) : void
    {
        $comment = Comments::getById($this->comment->getId());

        $I->assertNotNull($comment->getId());
    }

    /**
     * Test reply comment.
     *
     * @param IntegrationTester $I
     * @after getCommentData
     *
     * @return void
     */
    public function replyComment(IntegrationTester $I) : void
    {
        $commentReply = Random::generateDisplayName('reply-test', 100000);
        codecept_debug($this->comment->id);
        $reply = Comments::reply(
            $this->comment->getId(),
            $commentReply,
            Users::findFirst(-1)
        );
        codecept_debug($reply->parent_id);

        $I->assertEquals($reply->message, $commentReply);
        $I->assertEquals($reply->parent_id, $this->comment->getId());
    }

    /**
     * Test edit comment.
     *
     * @param IntegrationTester $I
     * @after getCommentData
     * @after getCommentsFromMessage
     *
     * @return void
     */
    public function deleteComment(IntegrationTester $I) : void
    {
        $I->assertTrue(
            Comments::delete(
                (string) $this->comment->getId(),
                new Users()
            )
        );
    }

    /**
     * Test comments Reactions.
     *
     * @param IntegrationTester $I
     * @before getCommentData
     *
     * @return void
     */
    public function commentReaction(IntegrationTester $I) : void
    {
        $user = Users::findFirst(1);
        $reactionName = Random::generateDisplayName('confused', 2000);
        Reactions::createReaction($reactionName, $user, '☺');
        $reaction = Reactions::addMessageReaction($reactionName, $user, $this->comment);
        $I->assertTrue($reaction instanceof UsersReactions);
        $I->assertFalse((bool)$reaction->is_deleted);

        $reaction = Reactions::addMessageReaction($reactionName, $user, $this->comment);
        $I->assertTrue($reaction instanceof UsersReactions);
        $I->assertTrue((bool)$reaction->is_deleted);
    }

    /**
     * Test Users Comments Interaction.
     *
     * @param IntegrationTester $I
     * @before getCommentData
     *
     * @return void
     */
    public function messageInteraction(IntegrationTester $I) : void
    {
        $user = Users::findFirst(-1);

        $I->assertTrue(
            Interactions::add($user, $this->comment, EnumsInteractions::REACT) instanceof UsersInteractions
        );
    }

    /**
     * Test method to get comments from a message.
     *
     * @param IntegrationTester $I
     * @before getCommentData
     *
     * @return void
     */
    public function getCommentsFromMessage(IntegrationTester $I) : void
    {
        $message = $this->comment->messages;
        $comments = Comments::getCommentsFromMessage($message);

        $I->assertNotEmpty($comments->toArray());
    }
}
