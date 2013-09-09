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

class FriendMedia {

    public $media_id,
            $friend_id;
      

    

    public function exchangeArray($data) {
        $this->user_id = (isset($data['user_id'])) ? $data['user_id'] : null;
        $this->friend_id = (isset($data['friend_id'])) ? $data['friend_id'] : null;
    }
 
}

?>
