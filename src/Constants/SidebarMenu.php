<?php

namespace EstebanSmolak19\CrudServiceGenerator\Constants;

class SidebarMenu {

   private static array $menu = [
        [
            'title' => '📁 Contrôleurs',
            'route' => 'controllers'
        ],
        [
            'title' => '⚙️ Services',
            'route' => 'services'    
        ],
   ];

   public static function all(): array
   {
        return self::$menu;
   }
}