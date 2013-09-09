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

class EventMedia {

    public $event_id,
            $media_id;
       

    public function exchangeArray($data) {
        $this->media_id = (isset($data['media_id'])) ? $data['media_id'] : null;
        $this->event_id = (isset($data['event_id'])) ? $data['event_id'] : null;
        
    }
 
}

?>
