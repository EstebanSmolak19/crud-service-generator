<?php

namespace EstebanSmolak19\CrudServiceGenerator\Constants;

class SidebarMenu {

   private static array $menu = [
     [
          'title' => 'nom',
          'route' => 'route'
     ],
   ];

   public static function all(): array
   {
        return self::$menu;
   }
}