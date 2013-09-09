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

class Media {

    public $media_id,
            $user_id,
            $is_profile_pic,
            $sync_status, 
            $metadata, 
            $report_flag, 
            $create_date, 
            $update_date;
      protected $inputFilter;   

    

    public function exchangeArray($data) {
        $this->user_id = (isset($data['user_id'])) ? $data['user_id'] : null;
        $this->media_id = (isset($data['media_id'])) ? $data['media_id'] : null;
        $this->is_profile_pic = (isset($data['is_profile_pic'])) ? $data['is_profile_pic'] : null;
        $this->sync_status = (isset($data['sync_status'])) ? $data['sync_status'] : null;
        $this->metadata = (isset($data['metadata'])) ? $data['metadata'] : null;
        $this->report_flag = (isset($data['report_flag'])) ? $data['report_flag'] : null;
        $this->create_date = (isset($data['create_date'])) ? $data['create_date'] : null;
        $this->update_date = (isset($data['update_date'])) ? $data['update_date'] : null;
    }
 // Add content to these methods:
    public function setInputFilter(InputFilterInterface $inputFilter)
    {
        throw new \Exception("Not used");
    }

    public function getInputFilter()
    {
        if (!$this->inputFilter) {
            $inputFilter = new InputFilter();
            $factory     = new InputFactory();

            $inputFilter->add($factory->createInput(array(
                'name'     => 'user_id',
                'required' => true,
                'filters'  => array(
                    array('name' => 'StripTags'),
                ),
            )));

            $this->inputFilter = $inputFilter;
        }

        return $this->inputFilter;
    }
}

?>
