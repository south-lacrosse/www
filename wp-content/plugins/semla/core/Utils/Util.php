<?php
/**
 * Utility methods called from multiple places
 */
namespace Semla\Utils;

class Util {
    public static function make_id($str) {
        return urlencode(strtr($str,[
            ' ' => '-', '(' => '',')' => '',
        ]));
    }
    /**
      * Return the fixtures sheet url for a given id, or from the options
      * NB: does not return /edit, just up to that
      */
     public static function get_fixtures_sheet_url($id = '') {
        if (!$id) {
            $id = get_option('semla_fixtures_sheet_id');
        }
        return 'https://docs.google.com/spreadsheets/d/' . $id . '/';
     }
}