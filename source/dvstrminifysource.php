<?php
/*######################################################
Devstratum JMP Minify Source
Plugin for minify CSS and JavaScript source code for CMS Joomla

Version: 1.0
License: MIT License
Author: Sergey Osipov
Copyright: (c) 2020 Sergey Osipov
Website: https://devstratum.ru
Email: info@devstratum.ru
Repo: https://github.com/devstratum/Devstratum-JMP-Minify-Source

     _                _             _
  __| | _____   _____| |_ _ __ __ _| |_ _   _ _ __ ___
 / _` |/ _ \ \ / / __| __| '__/ _` | __| | | | '_ ` _ \
| (_| |  __/\ V /\__ \ |_| | | (_| | |_| |_| | | | | | |
 \__,_|\___| \_/ |___/\__|_|  \__,_|\__|\__,_|_| |_| |_|

#######################################################*/

defined('_JEXEC') or die;
use MatthiasMullie\Minify;
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');
jimport('joomla.environment.uri');

class PlgSystemDvstrMinifySource extends JPlugin
{
    public function onAfterInitialise()
    {
        $app = JFactory::getApplication();
        if ($app->isAdmin()) return;

        // Include Clear Class
        if ($this->params->get('lib_behavior')) include __DIR__ . '/clearclass/behavior.php';
        if ($this->params->get('lib_bootstrap')) include __DIR__ . '/clearclass/bootstrap.php';
        if ($this->params->get('lib_jquery')) include __DIR__ . '/clearclass/jquery.php';
    }

    public function onBeforeCompileHead()
    {
        $app = JFactory::getApplication();
        if ($app->isAdmin()) return;

        $doc = JFactory::getDocument();
        $path_root = JPATH_ROOT . DIRECTORY_SEPARATOR;
        $path_cache = '/cache/' . $app->getTemplate() . '/';

        if (!is_dir($path_root . $path_cache)) {
            JFolder::create($path_root . $path_cache);
        }

        $minify_css = $this->params->get('minify_css');
        $minify_js = $this->params->get('minify_js');
        $unified_css = $this->params->get('unified_css');
        $defer_js = $this->params->get('defer_js');

        $exclude_js = ['jquery.min.js'];
        $uniname_css = $path_cache . $app->getTemplate() . '.min.css';

        if ($minify_css || $minify_js) {
            require_once __DIR__ . '/libraries/include_libs.php';
        }

        function safeFileName($path) {
            return JFile::makeSafe(JFile::getName($path));
        }

        function setMinExt($filename) {
            $ext = JFile::getExt($filename);
            $name = JFile::stripExt($filename);
            return str_replace('.min', '', $name) . '.min.' . $ext;
        }

        function checkUri($path) {
            $uri = JUri::getInstance($path);
            if ($uri->getScheme()) {
                return true;
            } else {
                return false;
            }
        }

        // Minify CSS
        if ($minify_css) {
            if ($unified_css) {
                $array_css = [];
                $minifier = new Minify\CSS();
                foreach ($doc->_styleSheets as $key => $item) {
                    if (!checkUri($key)) {
                        $minifier->add($path_root . $key);
                    } else {
                        $array_css[] = $key;
                    }
                }
                if ($minifier->minify($path_root . $uniname_css)) {
                    $doc->_styleSheets = [];
                    foreach ($array_css as $item) {
                        $doc->addStyleSheet($item);
                    }
                    $doc->addStyleSheet($uniname_css);
                }
            } else {
                $error_css = false;
                $array_css = [];
                foreach ($doc->_styleSheets as $key => $item) {
                    if (!checkUri($key)) {
                        $name_css = setMinExt(safeFileName($key));
                        $minifier = new Minify\CSS($path_root . $key);

                        if (!$minifier->minify($path_root . $path_cache . $name_css)) {
                            $error_css = true;
                            break;
                        } else {
                            $array_css[] = $name_css;
                        }

                    } else {
                        $array_css[] = $key;
                    }
                }

                if (!$error_css) {
                    $doc->_styleSheets = [];
                    foreach ($array_css as $item) {
                        if (!checkUri($item)) {
                            $doc->addStyleSheet($path_cache . $item);
                        } else {
                            $doc->addStyleSheet($item);
                        }
                    }
                }
            }
        }

        // Minify JS
        if ($minify_js) {
            $error_js = false;
            $array_js = [];
            foreach ($doc->_scripts as $key => $item) {

                if (!checkUri($key)) {
                    $name_js = setMinExt(safeFileName($key));
                    $minifier = new Minify\JS($path_root . $key);

                    if (!$minifier->minify($path_root . $path_cache . $name_js)) {
                        $error_js = true;
                        break;
                    } else {
                        $array_js[] = $name_js;
                    }

                } else {
                    $array_js[] = $key;
                }
            }

            if (!$error_js) {
                $doc->_scripts = [];
                foreach ($array_js as $item) {
                    if (!checkUri($item)) {
                        $doc->addScript($path_cache . $item);
                    } else {
                        $doc->addScript($item);
                    }
                }
            }
        }

        // Defer JS
        if ($defer_js) {
            foreach ($doc->_scripts as $key => &$item) {
                $name_js = safeFileName($key);
                $error_js = false;
                foreach ($exclude_js as $name) {
                    if ($name === $name_js) {
                        $error_js = true;
                        break;
                    }
                }

                if (!$error_js) {
                    $item['defer'] = true;
                }
            }
        }
    }
}