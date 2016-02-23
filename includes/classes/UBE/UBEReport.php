<?php

class UBEReport {

  // ------------------------------------------------------------------------------------------------
  // Записывает боевой отчет в БД
  /**
   * @param UBE $ube
   *
   * @return bool|string
   */
  function sn_ube_report_save($ube) {
    global $config;

    // Если уже есть ИД репорта - значит репорт был взят из таблицы. С таким мы не работаем
    if($ube->get_cypher()) {
      return false;
    }

    // Генерируем уникальный секретный ключ и проверяем наличие в базе
    do {
      $ube->report_cypher = sys_random_string(32);
    } while(doquery("SELECT ube_report_cypher FROM {{ube_report}} WHERE ube_report_cypher = '{$ube->report_cypher}' LIMIT 1 FOR UPDATE", true));

    // Инициализация таблицы для пакетной вставки информации
    $sql_perform = array(
      'ube_report_player' => array(
        array(
          '`ube_report_id`',
          '`ube_report_player_player_id`',
          '`ube_report_player_name`',
          '`ube_report_player_attacker`',
          '`ube_report_player_bonus_attack`',
          '`ube_report_player_bonus_shield`',
          '`ube_report_player_bonus_armor`',
        ),
      ),

      'ube_report_fleet' => array(
        array(
          '`ube_report_id`',
          '`ube_report_fleet_player_id`',
          '`ube_report_fleet_fleet_id`',
          '`ube_report_fleet_planet_id`',
          '`ube_report_fleet_planet_name`',
          '`ube_report_fleet_planet_galaxy`',
          '`ube_report_fleet_planet_system`',
          '`ube_report_fleet_planet_planet`',
          '`ube_report_fleet_planet_planet_type`',
          '`ube_report_fleet_resource_metal`',
          '`ube_report_fleet_resource_crystal`',
          '`ube_report_fleet_resource_deuterium`',
          '`ube_report_fleet_bonus_attack`',
          '`ube_report_fleet_bonus_shield`',
          '`ube_report_fleet_bonus_armor`',
        ),
      ),

      'ube_report_outcome_fleet' => array(
        array(
          '`ube_report_id`',
          '`ube_report_outcome_fleet_fleet_id`',
          '`ube_report_outcome_fleet_resource_lost_metal`',
          '`ube_report_outcome_fleet_resource_lost_crystal`',
          '`ube_report_outcome_fleet_resource_lost_deuterium`',
          '`ube_report_outcome_fleet_resource_dropped_metal`',
          '`ube_report_outcome_fleet_resource_dropped_crystal`',
          '`ube_report_outcome_fleet_resource_dropped_deuterium`',
          '`ube_report_outcome_fleet_resource_loot_metal`',
          '`ube_report_outcome_fleet_resource_loot_crystal`',
          '`ube_report_outcome_fleet_resource_loot_deuterium`',
          '`ube_report_outcome_fleet_resource_lost_in_metal`',
        ),
      ),

      'ube_report_outcome_unit' => array(
        array(
          '`ube_report_id`',
          '`ube_report_outcome_unit_fleet_id`',
          '`ube_report_outcome_unit_unit_id`',
          '`ube_report_outcome_unit_restored`',
          '`ube_report_outcome_unit_lost`',
          '`ube_report_outcome_unit_sort_order`',
        ),
      ),

      'ube_report_unit' => array(
        array(
          '`ube_report_id`',
          '`ube_report_unit_player_id`',
          '`ube_report_unit_fleet_id`',
          '`ube_report_unit_round`',
          '`ube_report_unit_unit_id`',
          '`ube_report_unit_count`',
          '`ube_report_unit_boom`',
          '`ube_report_unit_attack`',
          '`ube_report_unit_shield`',
          '`ube_report_unit_armor`',
          '`ube_report_unit_attack_base`',
          '`ube_report_unit_shield_base`',
          '`ube_report_unit_armor_base`',
          '`ube_report_unit_sort_order`',
        ),
      ),
    );

    // Сохраняем общую информацию о бое
    $sql_str = "INSERT INTO `{{ube_report}}`
    SET
      `ube_report_cypher` = '{$ube->report_cypher}',
      `ube_report_time_combat` = '" . date(FMT_DATE_TIME_SQL, $ube->combat_timestamp) . "',
      `ube_report_time_spent` = {$ube->time_spent},

      `ube_report_combat_admin` = " . (int)$ube->is_admin_in_combat . ",
      `ube_report_mission_type` = {$ube->mission_type_id},

      `ube_report_combat_result` = {$ube->combat_result},
      `ube_report_combat_sfr` = " . (int)$ube->is_small_fleet_recce . ",

      `ube_report_planet_id`          = " . (int)$ube->ube_planet_info[PLANET_ID] . ",
      `ube_report_planet_name`        = '" . db_escape($ube->ube_planet_info[PLANET_NAME]) . "',
      `ube_report_planet_size`        = " . (int)$ube->ube_planet_info[PLANET_SIZE] . ",
      `ube_report_planet_galaxy`      = " . (int)$ube->ube_planet_info[PLANET_GALAXY] . ",
      `ube_report_planet_system`      = " . (int)$ube->ube_planet_info[PLANET_SYSTEM] . ",
      `ube_report_planet_planet`      = " . (int)$ube->ube_planet_info[PLANET_PLANET] . ",
      `ube_report_planet_planet_type` = " . (int)$ube->ube_planet_info[PLANET_TYPE] . ",

      `ube_report_capture_result` = " . (int)$ube->capture_result . ", "
      . $ube->debris->report_generate_sql($config)
      . $ube->moon_calculator->report_generate_sql();

    doquery($sql_str);
//    $ube_report_id = $combat_data[UBE_REPORT_ID] = db_insert_id();
    $ube_report_id = db_insert_id();

    // Сохраняем общую информацию по игрокам
    $player_sides = $ube->players->get_player_sides();
    foreach($player_sides as $player_id => $player_side) {
      $sql_perform['ube_report_player'][] = array(
        $ube_report_id,
        $player_id,

        "'" . db_escape($ube->players[$player_id]->player_name_get()) . "'",
        $ube->players[$player_id]->player_side_get() == UBE_PLAYER_IS_ATTACKER ? 1 : 0,

        (float)$ube->players[$player_id]->player_bonus_get(UBE_ATTACK),
        (float)$ube->players[$player_id]->player_bonus_get(UBE_SHIELD),
        (float)$ube->players[$player_id]->player_bonus_get(UBE_ARMOR),
      );
    }

    // Всякая информация по флотам
    foreach($ube->fleet_list as $fleet_id => $UBEFleet) {
      // Сохраняем общую информацию по флотам
      $sql_perform['ube_report_fleet'][] = $UBEFleet->sql_generate_array($ube_report_id);

      // Сохраняем итоговую информацию по ресурсам флота - потеряно, выброшено, увезено
      $sql_perform['ube_report_outcome_fleet'][] = $ube->outcome->sql_generate_fleet_array($fleet_id, $ube_report_id);

      // Сохраняем результаты по юнитам - потеряно и восстановлено
      $ube->outcome->sql_generate_unit_array($UBEFleet, $sql_perform['ube_report_outcome_unit'], $ube_report_id);
    }


    // Сохраняем информацию о раундах
    $unit_sort_order = 0;
    foreach($ube->rounds->_container as $round => &$round_data) {
      foreach($ube->rounds[$round]->round_fleets as $fleet_id => &$fleet_data) {
        foreach($fleet_data[UBE_COUNT] as $unit_id => $unit_count) {
          $unit_sort_order++;

          $sql_perform['ube_report_unit'][] = array(
            $ube_report_id,
            // $ube->rounds[$round]->fleet_info[$fleet_id]->UBE_OWNER
            $ube->fleet_list[$fleet_id]->UBE_OWNER,
            $fleet_id,
            $round,

            $unit_id,
            $unit_count,
            (int)$fleet_data[UBE_UNITS_BOOM][$unit_id],

            $fleet_data[UBE_ATTACK][$unit_id],
            $fleet_data[UBE_SHIELD][$unit_id],
            $fleet_data[UBE_ARMOR][$unit_id],

            $fleet_data[UBE_ATTACK_BASE][$unit_id],
            $fleet_data[UBE_SHIELD_BASE][$unit_id],
            $fleet_data[UBE_ARMOR_BASE][$unit_id],

            $unit_sort_order,
          );
        }
      }
    }

    // Пакетная вставка данных
    foreach($sql_perform as $table_name => $table_data) {
      if(count($table_data) < 2) {
        continue;
      }
      foreach($table_data as &$record_data) {
        $record_data = '(' . implode(',', $record_data) . ')';
      }
      $fields = $table_data[0];
      unset($table_data[0]);
      doquery("INSERT INTO {{{$table_name}}} {$fields} VALUES " . implode(',', $table_data));
    }

    return $ube->report_cypher;
  }


  // ------------------------------------------------------------------------------------------------
  // Читает боевой отчет из БД
  /**
   * @param $report_cypher
   *
   * @return string|UBE
   */
  function sn_ube_report_load($report_cypher) {
    $report_cypher = db_escape($report_cypher);

    $report_row = doquery("SELECT * FROM {{ube_report}} WHERE ube_report_cypher = '{$report_cypher}' LIMIT 1", true);
    if(!$report_row) {
      return UBE_REPORT_NOT_FOUND;
    }

    $ube = new UBE();
    $ube->load_from_report_row($report_row, $report_cypher);

    return $ube;
  }


// ------------------------------------------------------------------------------------------------
// Генерирует данные для отчета из разобранных данных боя

  function ube_report_generate_side($side_fleet, UBE $ube, &$template_result) {
    global $lang;

    if(empty($side_fleet) || !is_array($side_fleet)) {
      return;
    }

    foreach($side_fleet as $fleet_id => $temp) {
      $fleet_owner_id = $ube->fleet_list[$fleet_id]->UBE_OWNER;
      $fleet_outcome = &$ube->outcome->outcome_fleets[$fleet_id];

      $template_result['.']['loss'][] = array(
        'ID'          => $fleet_id,
        'NAME'        => $ube->players[$fleet_owner_id]->player_name_get(),
        'IS_ATTACKER' => $ube->players[$fleet_owner_id]->player_side_get() == UBE_PLAYER_IS_ATTACKER,
        '.'           => array(
          'param' => array_merge(
            $this->sn_ube_report_table_render($fleet_outcome[UBE_DEFENCE_RESTORE], 'ube_report_info_restored'),
            $this->sn_ube_report_table_render($fleet_outcome[UBE_UNITS_LOST], 'ube_report_info_loss_final'),
            $this->sn_ube_report_table_render($fleet_outcome[UBE_RESOURCES_LOST], 'ube_report_info_loss_resources'),
            $this->sn_ube_report_table_render($fleet_outcome[UBE_CARGO_DROPPED], 'ube_report_info_loss_dropped'),
            $this->sn_ube_report_table_render($fleet_outcome[UBE_RESOURCES_LOOTED], $ube->players[$fleet_owner_id]->player_side_get() == UBE_PLAYER_IS_ATTACKER ? 'ube_report_info_loot_lost' : 'ube_report_info_loss_gained'),
            $this->sn_ube_report_table_render($fleet_outcome[UBE_RESOURCES_LOST_IN_METAL], 'ube_report_info_loss_in_metal')
          ),
        ),
      );
    }
  }

  /**
   * @param UBE $ube
   * @param     $template_result
   */
  function sn_ube_report_generate(UBE $ube, &$template_result) {
    if(!is_object($ube)) {
      return;
    }

    global $lang;

    // Обсчитываем результаты боя из начальных данных
    // Генерируем отчет по флотам
    $round_count = $ube->rounds->count();
    for($round = 1; $round <= $round_count - 1; $round++) {
      $round_template = array(
        'NUMBER' => $round,
        '.'      => array(
          'fleet' => $this->sn_ube_report_round_fleet($ube, $round),
        ),
      );
      $template_result['.']['round'][] = $round_template;
    }

    // Боевые потери флотов
    $this->ube_report_generate_side($ube->outcome->fleet_attackers, $ube, $template_result);
    $this->ube_report_generate_side($ube->outcome->fleet_defenders, $ube, $template_result);

    // Обломки
    $debris = array();
    foreach(array(RES_METAL, RES_CRYSTAL) as $resource_id) {
      if($resource_amount = $ube->debris->debris_get_resource($resource_id)) {
        $debris[] = array(
          'NAME'   => $lang['tech'][$resource_id],
          'AMOUNT' => pretty_number($resource_amount),
        );
      }
    }

// TODO: $combat_data[UBE_OPTIONS][UBE_COMBAT_ADMIN] - если админский бой не генерировать осколки и не делать луну. Сделать серверную опцию

    // Координаты, тип и название планеты - если есть
//R  $planet_owner_id = $combat_data[UBE_FLEETS][0][UBE_OWNER];
    if(isset($ube->ube_planet_info)) {
      $template_result += $ube->ube_planet_info;
      $template_result[PLANET_NAME] = str_replace(' ', '&nbsp;', htmlentities($template_result[PLANET_NAME], ENT_COMPAT, 'UTF-8'));
    }

    $template_result += array(
      'MICROTIME'         => $ube->get_time_spent(),
      'COMBAT_TIME'       => $ube->combat_timestamp ? $ube->combat_timestamp + SN_CLIENT_TIME_DIFF : 0,
      'COMBAT_TIME_TEXT'  => date(FMT_DATE_TIME, $ube->combat_timestamp + SN_CLIENT_TIME_DIFF),
      'COMBAT_ROUNDS'     => $round_count - 1,
      'UBE_MISSION_TYPE'  => $ube->mission_type_id,
      'UBE_REPORT_CYPHER' => $ube->get_cypher(),

      'PLANET_TYPE_TEXT' => $lang['sys_planet_type_sh'][$template_result['PLANET_TYPE']],

      'UBE_CAPTURE_RESULT'      => $ube->capture_result,
      'UBE_CAPTURE_RESULT_TEXT' => $lang['ube_report_capture_result'][$ube->capture_result],

      'UBE_SFR'           => $ube->is_small_fleet_recce,
      'UBE_COMBAT_RESULT' => $ube->combat_result,

      'MT_DESTROY'             => MT_DESTROY,
      'UBE_COMBAT_RESULT_WIN'  => UBE_COMBAT_RESULT_WIN,
      'UBE_COMBAT_RESULT_LOSS' => UBE_COMBAT_RESULT_LOSS,
    );
    $template_result += $ube->moon_calculator->template_generate_array();

    $template_result['.']['debris'] = $debris;
  }

  // ------------------------------------------------------------------------------------------------
// Парсит инфу о раунде для темплейта
  function sn_ube_report_round_fleet(UBE $ube, $round) {
    global $lang;

    $round_template = array();
    $objRound = $ube->rounds[$round];
    foreach(array(UBE_ATTACKERS, UBE_DEFENDERS) as $side) {
      $side_array = $side == UBE_ATTACKERS ? $objRound->UBE_ATTACKERS : $objRound->UBE_ATTACKERS;
      empty($side_array) ? $objRound->UBE_ATTACKERS = array() : false;
      foreach($side_array[UBE_ATTACK] as $fleet_id => $temp) {
        $fleet_data = &$objRound->round_fleets[$fleet_id];
        $fleet_template = array(
          'ID'          => $fleet_id,
          'IS_ATTACKER' => $side == UBE_ATTACKERS,
          'PLAYER_NAME' => $ube->players[$ube->fleet_list[$fleet_id]->UBE_OWNER]->player_name_get(true),
        );

        if(is_array($ube->fleet_list[$fleet_id]->UBE_PLANET)) {
          $fleet_template += $ube->fleet_list[$fleet_id]->UBE_PLANET;
          $fleet_template[PLANET_NAME] = $fleet_template[PLANET_NAME] ? htmlentities($fleet_template[PLANET_NAME], ENT_COMPAT, 'UTF-8') : '';
          $fleet_template['PLANET_TYPE_TEXT'] = $lang['sys_planet_type_sh'][$fleet_template['PLANET_TYPE']];
        }

        foreach($fleet_data[UBE_COUNT] as $unit_id => $unit_count) {
          $shields_original = $fleet_data[UBE_SHIELD_BASE][$unit_id] * $ube->rounds[$round - 1]->round_fleets[$fleet_id][UBE_COUNT][$unit_id];
          $ship_template = array(
            'ID'          => $unit_id,
            'NAME'        => $lang['tech'][$unit_id],
            'ATTACK'      => pretty_number($fleet_data[UBE_ATTACK][$unit_id]),
            'SHIELD'      => pretty_number($shields_original),
            'SHIELD_LOST' => pretty_number($shields_original - $fleet_data[UBE_SHIELD][$unit_id]),
            'ARMOR'       => pretty_number($ube->rounds[$round - 1]->round_fleets[$fleet_id][UBE_ARMOR][$unit_id]),
            'ARMOR_LOST'  => pretty_number($ube->rounds[$round - 1]->round_fleets[$fleet_id][UBE_ARMOR][$unit_id] - $fleet_data[UBE_ARMOR][$unit_id]),
            'UNITS'       => pretty_number($ube->rounds[$round - 1]->round_fleets[$fleet_id][UBE_COUNT][$unit_id]),
            'UNITS_LOST'  => pretty_number($ube->rounds[$round - 1]->round_fleets[$fleet_id][UBE_COUNT][$unit_id] - $fleet_data[UBE_COUNT][$unit_id]),
            'UNITS_BOOM'  => pretty_number($fleet_data[UBE_UNITS_BOOM][$unit_id]),
          );

          $fleet_template['.']['ship'][] = $ship_template;
        }

        $round_template[] = $fleet_template;
      }
    }

    return $round_template;
  }

// ------------------------------------------------------------------------------------------------
// Рендерит таблицу общего результата боя
  function sn_ube_report_table_render(&$array, $lang_header_index) {
    global $lang;

    $result = array();
    if(!empty($array)) {
      foreach($array as $unit_id => $unit_count) {
        if($unit_count) {
          $result[] = array(
            'NAME' => $lang['tech'][$unit_id],
            'LOSS' => pretty_number($unit_count),
          );
        }
      }
      if($lang_header_index && count($result)) {
        array_unshift($result, array('NAME' => $lang[$lang_header_index]));
      }
    }

    return $result;
  }

}