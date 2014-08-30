<?php

class Forums_IndexController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $themeTable = new Forums_Themes();
        $topicsTable = new Forums_Topics();
        $comments = new Comments();

        $user = $this->_helper->user()->get();
        $isModearator = $user &&
            $this->_helper->acl()->inheritsRole($user->role, 'moder');

        $select = $themeTable->select(true)
            ->where('parent_id IS NULL')
            ->order('position');

        if (!$isModearator) {
            $select->where('not is_moderator');
        }

        $themes = array();

        foreach ($themeTable->fetchAll($select) as $row) {
            $lastMessage = false;
            $lastTopic = $topicsTable->fetchRow(
                $topicsTable->select(true)
                    ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', null)
                    ->where('forums_topics.status IN (?)', array(Forums_Topics::STATUS_NORMAL, Forums_Topics::STATUS_CLOSED))
                    ->where('forums_theme_parent.parent_id = ?', $row->id)
                    ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', null)
                    ->where('comment_topic.type_id = ?', Comment_Message::FORUMS_TYPE_ID)
                    ->order('comment_topic.last_update DESC')
            );
            if ($lastTopic) {
                $lastMessage = $comments->getLastMessageRow(Comment_Message::FORUMS_TYPE_ID, $lastTopic->id);
            }

            $subthemes = array();

            $select = $themeTable->select()
                ->where('parent_id = ?', $row->id)
                ->order('position');

            if (!$isModearator) {
                $select->where('not is_moderator');
            }

            foreach ($themeTable->fetchAll($select) as $srow) {
                $subthemes[] = array(
                    'name' => $srow->caption,
                    'url'  => $this->_helper->url->url(array(
                        'controller' => 'theme',
                        'action'     => 'theme',
                        'theme_id'   => $srow->id
                    )),
                );
            }

            $themes[] = array(
                'url'         => $this->_helper->url->url(array(
                    'controller' => 'theme',
                    'action'     => 'theme',
                    'theme_id'   => $row->id
                )),
                'name'        => $row->caption,
                'description' => $row->description,
                'lastTopic'   => $lastTopic,
                'lastMessage' => $lastMessage,
                'topics'      => $row->topics,
                'messages'    => $row->messages,
                'subthemes'   => $subthemes
            );
        }

        $this->view->themes = $themes;
    }
}