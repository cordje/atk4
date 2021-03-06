<?php // vim:ts=4:sw=4:et:fdm=marker
/**
 * This is the most appropriate API file for your web application. It builds on top of ApiWeb
 * and introduces concept of "Pages" on top of "Layout" concept defined in ApiWeb.
 *
 * @link http://agiletoolkit.org/learn/understand/api
 * @link http://agiletoolkit.org/learn/template
*//*
==ATK4===================================================
   This file is part of Agile Toolkit 4 
    http://agiletoolkit.org/
  
   (c) 2008-2012 Romans Malinovskis <romans@agiletoolkit.org>
   Distributed under Affero General Public License v3
   
   See http://agiletoolkit.org/about/license
 =====================================================ATK4=*/
class ApiFrontend extends ApiWeb{

    /** When page is determined, it's class instance is created and stored in here */
    public $page_object=null;

    /** @depreciated (might be refactored) - type of returned content */
    public $content_type='page';    // content type: rss/page/etc

    /** Class which is used for static pages */
    public $page_class='Page';

    // {{{ Layout Implementation
    /** Content in the global (shared.html) template is rendered by page object. This method loads either class or static file */
    function initLayout(){
        parent::initLayout();
        $this->addLayout('Content');
        $this->upgradeChecker();
    }
    function layout_Content(){
        // required class prefix depends on the content_type
        // This function initializes content. Content is page-dependant
        $page=str_replace('/','_',$this->page);
        $page=str_replace('-','',$page);
        $class=$this->content_type.'_'.$page;

        if($this->api->page_object)return;   // page is already initialized;

        if(method_exists($this,$class)){
            // for page we add Page class, for RSS - RSSchannel
            // TODO - this place is suspicious. Can it call arbitary function from API?
            $this->page_object=$this->add($this->content_type=='page'?$this->page_class:'RSSchannel',$this->page);
            $this->$class($this->page_object);
        }else{
            try{
                loadClass($class);
            }catch(PathFinder_Exception $e){


                // page not found, trying to load static content
                try{
                    $this->loadStaticPage($this->page);
                }catch(PathFinder_Exception $e2){

                    $class_parts=explode('_',$page);
                    $funct_parts=array();
                    while($class_parts){
                        array_unshift($funct_parts,array_pop($class_parts));
                        $fn='page_'.join('_',$funct_parts);
                        $in='page_'.join('_',$class_parts);
                        try {
                            loadClass($in);
                        }catch(PathFinder_Exception $e2){
                            continue;
                        }
                        // WorkAround for PHP5.2.12+ PHP bug #51425
                        $tmp=new $in;
                        if(!method_exists($tmp,$fn) && !method_exists($tmp,'subPageHandler'))continue;

                        $this->page_object=$this->add($in,$page);
                        if(method_exists($tmp,$fn)){
                            $this->page_object->$fn();
                        }elseif(method_exists($tmp,'subPageHandler')){
                            if($this->page_object->subPageHandler(join('_',$funct_parts))===false)break;
                        }
                        return;
                    }


                    // throw original error
                    $this->pageNotFound($e);
                }
                return;
            }
            // i wish they implemented "finally"
            $this->page_object=$this->add($class,$page,'Content');
            if(method_exists($this->page_object,'initMainPage'))$this->page_object->initMainPage();
            if(method_exists($this->page_object,'page_index'))$this->page_object->page_index();
        }
    }
    /** This method is called as a last resort, when page is not found. It receives the exception with the actual error */
    function pageNotFound($e){
        throw $e;
    }
    /** Attempts to load static page. Raises exception if not found */
    protected function loadStaticPage($page){
        try{
            $t='page/'.str_replace('_','/',strtolower($page));
            $this->template->findTemplate($t);

            $this->page_object=$this->add($this->page_class,$page,'Content',array($t));
        }catch(PathFinder_Exception $e2){

            $t='page/'.strtolower($page);
            $this->template->findTemplate($t);
            $this->page_object=$this->add($this->page_class,$page,'Content',array($t));
        }

        return $this->page_object;
    }
    // }}}

    // {{{ Obsolete functions
    /** @obsolete - does nothing */
    function execute(){
        try{
            parent::execute();
        }catch(Exception $e){
            $this->caughtException($e);
        }
    }
    /** @obsolete */
    function getRSSURL($rss,$args=array()){
        $tmp=array();
        foreach($args as $arg=>$val){
            if(!isset($val) || $val===false)continue;
            if(is_array($val)||is_object($val))$val=serialize($val);
            $tmp[]="$arg=".urlencode($val);
        }
        return
            $rss.'.xml'.($tmp?'?'.join('&',$tmp):'');
    }
    // }}}
}
