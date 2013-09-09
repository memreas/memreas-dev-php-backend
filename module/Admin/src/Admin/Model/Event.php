<?php

/**
 * Description of Users
 * @author shivani
 */

namespace Admin\Model;
use Zend\InputFilter\Factory as InputFactory;
use Zend\InputFilter\InputFilter;
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\InputFilter\InputFilterInterface;

class Event {

    public $event_id,
            $user_id,
            $name,
            $location,
            $date,
            $friends_can_post,
            $friends_can_share,
            $public,
            $viewable_from,
            $viewable_to,
            $self_destruct,
            $create_time,
            $update_time;
       

    public function exchangeArray($data) {
        $this->user_id = (isset($data['user_id'])) ? $data['user_id'] : null;
        $this->event_id = (isset($data['event_id'])) ? $data['event_id'] : null;
        $this->name = (isset($data['name'])) ? $data['name'] : null;
        $this->location = (isset($data['location'])) ? $data['location'] : null;
        $this->date = (isset($data['date'])) ? $data['date'] : null;
        $this->friends_can_post = (isset($data['friends_can_post'])) ? $data['friends_can_post'] : null;
        $this->friends_can_share = (isset($data['friends_can_share'])) ? $data['friends_can_share'] : null;
        $this->public = (isset($data['public'])) ? $data['public'] : null;
        $this->viewable_from = (isset($data['viewable_from'])) ? $data['viewable_from'] : null;
        $this->self_destruct = (isset($data['self_destruct'])) ? $data['self_destruct'] : null;
        
        $this->create_time = (isset($data['create_time'])) ? $data['create_time'] : null;
        $this->update_time = (isset($data['update_time'])) ? $data['update_time'] : null;
    }
 
}

?>
