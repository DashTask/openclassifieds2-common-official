<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Panel_Content extends Auth_Controller {

    /**
     * from a type returns the translated tezt
     * @param  string $type 
     * @return string       translated
     */
    public static function translate_type($type)
    {
         switch ($type) {
            case 'email':
                return  __('Email');
                break;
            case 'help':
                return  __('FAQ');
                break;
            case 'email':
            default:
                return  __('Page');
                break;
        } 
    }

    //list index email
    public function action_email()
    {
        $this->action_list('email');
    }
    //list index page
    public function action_page()
    {
        $this->action_list('page');
    }
    //list index FAQ
    public function action_help()
    {
        $this->action_list('help');
    }

    /**
     * action: LIST
     */
    public function action_list($type = NULL)
    {
        $this->template->scripts['footer'][] = 'js/oc-panel/crud/index.js';

        if ($type===NULL)
            $type = core::get('type');
        
        $site = self::translate_type($type);

        $locale = core::get('locale_select', core::config('i18n.locale'));

        Breadcrumbs::add(Breadcrumb::factory()->set_title($site));  
        $this->template->title = $site;

        $contents = Model_Content::get_contents($type,$locale);

        
        $this->template->content = View::factory('oc-panel/pages/content/list',array('contents'=>$contents, 
                                                                                        'type'=>$type, 
                                                                                        'locale_list'=>i18n::get_languages(),
                                                                                        'locale' => $locale));
    }

    /**
     * action: EDIT
     */
    public function action_create()
    {
        $type = core::get('type');
        $site = self::translate_type($type);

        Breadcrumbs::add(Breadcrumb::factory()->set_title($site)->set_url(Route::url('oc-panel',array('controller'  => 'content','action'=>$type))));
        Breadcrumbs::add(Breadcrumb::factory()->set_title(__('Create').' '.$site));
        $content = new Model_Content();

        $languages = i18n::get_languages();

        $this->template->content = View::factory('oc-panel/pages/content/create', array('locale'=>$languages, 
                                                                                        'type'=>$type));

        if($p = $this->request->post())
        {
            foreach ($p as $name => $value) 
            {
                //for description we accept the HTML as comes...a bit risky but only admin can
                if ($name=='description')
                {
                    $content->description = Kohana::$_POST_ORIG['description'];
                }
                elseif($name != 'submit')
                {
                    $content->$name = $value;
                }
            }
            // if status is not checked, it is not set as POST response

            $content->status = (isset($p['status']))?1:0;
            if(!isset($p['seotitle']))
            $content->seotitle = $content->gen_seotitle($this->request->post('title'));
        	else
        	$content->seotitle = $p['seotitle'];

            try 
            {
                $content->save();
                Alert::set(Alert::SUCCESS, $this->request->post('type').' '.__('is created').'. '.__('Please to see the changes delete the cache')
                    .'<br><a class="btn btn-primary btn-mini" href="'.Route::url('oc-panel',array('controller'=>'tools','action'=>'cache')).'?force=1">'
                    .__('Delete All').'</a>');
                HTTP::redirect(Route::url('oc-panel',array('controller'  => 'content','action'=>'list')).'?type='.$p['type'].'&locale_select='.$p['locale']);
            } 
            catch (Exception $e) 
            {
                Alert::set(Alert::ERROR, $e->getMessage());
                HTTP::redirect(Route::url('oc-panel',array('controller'  => 'content','action'=>'list')).'?type='.$p['type'].'&locale_select='.$p['locale']);
            }
        }

    }

    /**
     * action: EDIT
     */
    public function action_edit()
    {

        $id = $this->request->param('id');
        $content = new Model_Content($id);

        $type = $content->type;
        $site = self::translate_type($type);

        Breadcrumbs::add(Breadcrumb::factory()->set_title($site)->set_url(Route::url('oc-panel',array('controller'  => 'content','action'=>$type))));
        Breadcrumbs::add(Breadcrumb::factory()->set_title(__('Edit').' '.$site));
        
        $locale = $content->locale;
        if ($content->loaded())
        {
            $languages = i18n::get_languages();

            $this->template->content = View::factory('oc-panel/pages/content/edit',array('cont'=>$content,'locale'=>$languages));

            if($p = $this->request->post())
            {
                foreach ($p as $name => $value) 
                {
                    //for description we accept the HTML as comes...a bit risky but only admin can
                    if ($name=='description')
                    {
                        $content->description = Kohana::$_POST_ORIG['description'];
                    }
                    elseif($name != 'submit')
                    {
                        $content->$name = $value;
                    }
                }
                // if status is not checked, it is not set as POST response
                $content->status = (isset($p['status']))?1:0;
                if ($type!='email')//email we do not update the seoname if not wont find the email to be sent :S
                    $content->seotitle = $content->gen_seotitle($this->request->post('title'));

                try 
                {
                    $content->save();
                    Alert::set(Alert::SUCCESS, $content->type.' '.__('is edited'));
                    HTTP::redirect(Route::url('oc-panel',array('controller'  => 'content','action'=>'edit', 'id'=>$content->id_content)));
                } 
                catch (Exception $e) 
                {
                    Alert::set(Alert::ERROR, $e->getMessage());
                }
            }
        }
        else
        {
            Alert::set(Alert::INFO, __('Failed to load content'));
            HTTP::redirect(Route::url('oc-panel',array('controller'  => 'content','action'=>'edit')).'?type='.$type.'&locale_select='.$locale); 
        }
    }

    /**
     * action: DELETE
     */
    public function action_delete()
    {
        $this->auto_render = FALSE;
        
        $id = $this->request->param('id');
        $content = new Model_Content($id);
        
        if ($content->loaded())
        {
            try
            {
                $content->delete();
                $this->template->content = 'OK';
            }
            catch (Exception $e)
            {
                 Alert::set(Alert::ERROR, $e->getMessage());
            }
        }
       
    }


    public function action_copy()
    {
        $from_locale   = core::get('from_locale',i18n::$locale_default);
        $to_locale     = core::get('to_locale');
        $type          = core::get('type');

        
        if (Model_Content::copy($from_locale,$to_locale,$type))
            Alert::set(Alert::SUCCESS, sprintf(__('%s copied from %s to %s'),$type,$from_locale,$to_locale));
        else
            Alert::set(Alert::INFO, sprintf(__('We can not copy the %s since the locale %s already has existing %s'),$type, $to_locale, $type));
    
        
        HTTP::redirect(Route::url('oc-panel',array('controller'  => 'content','action'=>'list')).'?type='.$type.'&locale_select='.$to_locale);

    }


}