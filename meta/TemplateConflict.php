<?php
/**
 * FarmSync DokuWiki Plugin
 *
 * @author Michael Große <grosse@cosmocode.de>
 * @license GPL 2
 */

namespace dokuwiki\plugin\farmsync\meta;

use dokuwiki\Form\Form;

/**
 * Display conflicts in template updates
 */
class TemplateConflict extends UpdateResults {

    /**
     * Adds conflict resolution form
     *
     * @return string
     */
    public function getResultLine() {
        $result = parent::getResultLine();
        $form = new Form();
        $form->attrs(array('data-animal' => $this->getAnimal(), "data-page" => $this->getItem(), "data-type" => 'template'));

        $form->addButton("theirs", $this->helper->getLang('button:keep'));
        $form->addButton("override", $this->helper->getLang('button:overwrite'));
        $form->addButton("diff", $this->helper->getLang('button:diff'));

        $result .= $form->toHTML();
        return $result;
    }
}
