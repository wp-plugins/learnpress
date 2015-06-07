<?php

/**
 * Class LPR_Admin_Settings
 */
class LPR_Admin_Settings{
    protected $_key = '';
    public $_options = false;
    function __construct( $key ){
        if( !$key ){
            wp_die();
        }
        $this->_key = $key;
        $this->_options = (array)get_option( $this->_key );
    }

    function set( $name, $value ){
        $this->_set_option( $this->_options, $name, $value, true );
    }

    private function _set_option( &$obj, $var, $value, $recurse = false ){
        $var = (array)explode('.', $var);
        $current_var = array_shift( $var );
        if( is_object( $obj ) ){
            $obj_vars = get_object_vars( $obj );
            if( array_key_exists( $current_var, $obj_vars ) ){ // isset( $obj->{$current_var} ) ){
                if( count( $var ) ){
                    if( is_object( $obj->$current_var ) ){
                        $obj->$current_var = new stdClass();
                    }else{
                        $obj->$current_var = array();
                    }
                    $this->_set_option( $obj->$current_var, join('.', $var ), $value, $recurse );
                }else{
                    $obj->$current_var = $value;
                }
            }else{
                if( $recurse ){
                    if( count( $var ) ){
                        $next_var = reset($var);
                        if( is_object( $obj->$current_var ) ){
                            $obj->$current_var = new stdClass();
                        }else{
                            $obj->$current_var = array();
                        }
                        $this->_set_option( $obj->$current_var, join('.', $var ), $value, $recurse );
                    }else{
                        $obj->$current_var = $value;
                    }
                }else{
                    $obj->$current_var = $value;
                }
            }
        }else if( is_array( $obj ) ){
            if( array_key_exists( $current_var, $obj ) ){
                if( count( $var ) ){
                    $obj[$current_var] = array();
                    $this->_set_option( $obj[$current_var], join('.', $var ), $value, $recurse );
                }else{
                    $obj[$current_var] = $value;
                }
            }else{
                if( $recurse ){
                    if( count( $var ) ){
                        $next_var = reset($var);
                        $obj[$current_var] = array();
                        $this->_set_option( $obj[$current_var], join('.', $var ), $value, $recurse );
                    }else{
                        $obj[$current_var] = $value;
                    }
                }else{
                    $obj[$current_var] = $value;
                }
            }
        }
    }

    function get( $var, $default = null ){
        return $this->_get_option( $this->_options, $var, $default );
    }

    function _get_option( $obj, $var, $default = null ){
        $var = (array)explode('.', $var);
        $current_var = array_shift( $var );
        if( is_object( $obj ) ){
            if( isset( $obj->{$current_var} ) ){
                if( count( $var ) ){
                    return $this->_get_option( $obj->{$current_var}, join('.', $var ), $default );
                }else{
                    return $obj->{$current_var};
                }
            }else{
                return $default;
            }
        }else{
            if( isset( $obj[$current_var] ) ){
                if( count( $var ) ){
                    return $this->_get_option( $obj[$current_var], join('.', $var ), $default );
                }else{
                    return $obj[$current_var];
                }
            }else{
                return $default;
            }
        }
        return $default;
    }

    function bind( $new ){
        if( is_object( $new ) ) $new = (array)$new;
        if( is_array( $new ) ){
            foreach( $new as $k => $v ){
                $this->set( $k, $v );
            }
        }
    }

    function update(){
        update_option( $this->_key, $this->_options );
    }

    static function instance( $key ){
        static $instances = array();
        $key = '_lpr_settings_' . $key;
        if( empty( $instances[$key] ) ){
            $instances[$key] = new LPR_Admin_Settings( $key );
        }
        return $instances[$key];
    }
}

if( !function_exists( 'learn_press_admin_settings' ) ){
    function learn_press_admin_settings( $key ){
        return LPR_Admin_Settings::instance( $key );
    }
}