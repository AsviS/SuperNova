<?php

// Compare function to sort fleet in time order
use DBStatic\DBStaticFleetACS;

function tpl_assign_fleet_compare($a, $b) {
  if($a['fleet']['OV_THIS_PLANET'] == $b['fleet']['OV_THIS_PLANET']) {
    if($a['fleet']['OV_LEFT'] == $b['fleet']['OV_LEFT']) {
      return 0;
    }

    return ($a['fleet']['OV_LEFT'] < $b['fleet']['OV_LEFT']) ? -1 : 1;
  } else {
    return $a['fleet']['OV_THIS_PLANET'] ? -1 : 1;
  }
}

/**
 * @param template $template
 * @param array    $fleets
 * @param string   $js_name
 */
function tpl_assign_fleet(&$template, $fleets, $js_name = 'fleets') {
  if(!$fleets) {
    return;
  }

  usort($fleets, 'tpl_assign_fleet_compare');

  foreach($fleets as $fleet_data) {
    $template->assign_block_vars($js_name, $fleet_data['fleet']);

    if($fleet_data['ships']) {
      foreach($fleet_data['ships'] as $ship_data) {
        $template->assign_block_vars("{$js_name}.ships", $ship_data);
      }
    }
  }
}

// function that parses internal fleet representation (as array(id => count))
function tpl_parse_fleet_sn($fleet, $fleet_id) {
  global $user;

  $user_data = &$user;

  $return['fleet'] = array(
    'ID' => $fleet_id,

    'METAL'     => $fleet[RES_METAL],
    'CRYSTAL'   => $fleet[RES_CRYSTAL],
    'DEUTERIUM' => $fleet[RES_DEUTERIUM],
  );

  foreach($fleet as $ship_id => $ship_amount) {
    if(in_array($ship_id, classSupernova::$gc->groupFleet)) {
      $single_ship_data = get_ship_data($ship_id, $user_data);
      $return['ships'][$ship_id] = array(
        'ID'          => $ship_id,
        'NAME'        => classLocale::$lang['tech'][$ship_id],
        'AMOUNT'      => $ship_amount,
        'CONSUMPTION' => $single_ship_data['consumption'],
        'SPEED'       => $single_ship_data['speed'],
        'CAPACITY'    => $single_ship_data['capacity'],
      );
    }
  }

  return $return;
}

// Used in UNIT_CAPTAIN
/**
 * @param Fleet $objFleet
 * @param       $index
 * @param bool  $user_data
 *
 * @return array
 */
function tplParseFleetObject(Fleet $objFleet, $index, $user_data = false) { return sn_function_call(__FUNCTION__, array($objFleet, $index, $user_data, &$result)); }

/**
 * @param Fleet $objFleet
 * @param       $index
 * @param bool  $user_data
 * @param       $result
 *
 * @return array
 */
function sn_tplParseFleetObject(Fleet $objFleet, $index, $user_data = false, &$result) {
  global $user;

  $result = array();

  if(!$user_data) {
    $user_data = $user;
  }

  if(!$objFleet->isReturning() && $objFleet->mission_type == MT_ACS) {
    $aks = DBStaticFleetACS::db_acs_get_by_group_id($objFleet->group_id);
  }

  $spy_level = $user['id'] == $objFleet->playerOwnerId ? 100 : GetSpyLevel($user);

  $fleet_resources = $objFleet->resourcesGetList();
  $result['fleet'] = isset($result['fleet']) ? $result['fleet'] : array();
  $result['fleet'] = array(
    'NUMBER' => $index,

    'ID'           => $objFleet->dbId,
    'OWNER'        => $objFleet->playerOwnerId,
    'TARGET_OWNER' => $objFleet->target_owner_id,

    'MESSAGE'      => $objFleet->isReturning(),
    'MISSION'      => $objFleet->mission_type,
    'MISSION_NAME' => classLocale::$lang['type_mission'][$objFleet->mission_type],
    'ACS'          => !empty($aks['name']) ? $aks['name'] : (!empty($objFleet->group_id) ? $objFleet->group_id : ''),
    'AMOUNT'       => $spy_level >= 4 ? (pretty_number($objFleet->shipsGetTotal()) . (array_sum($fleet_resources) ? '+' : '')) : '?',

    'METAL'     => $spy_level >= 8 ? $fleet_resources[RES_METAL] : 0,
    'CRYSTAL'   => $spy_level >= 8 ? $fleet_resources[RES_CRYSTAL] : 0,
    'DEUTERIUM' => $spy_level >= 8 ? $fleet_resources[RES_DEUTERIUM] : 0,

    'START_TYPE_TEXT_SH' => classLocale::$lang['sys_planet_type_sh'][$objFleet->fleet_start_type],
    'START_COORDS'       => "[{$objFleet->fleet_start_galaxy}:{$objFleet->fleet_start_system}:{$objFleet->fleet_start_planet}]",
    'START_TIME_TEXT'    => date(FMT_DATE_TIME, $objFleet->time_return_to_source + SN_CLIENT_TIME_DIFF),
    'START_LEFT'         => floor($objFleet->time_return_to_source + 1 - SN_TIME_NOW),
    'START_URL'          => uni_render_coordinates_href($objFleet->launch_coordinates_typed(), '', 3),

    'END_TYPE_TEXT_SH' => classLocale::$lang['sys_planet_type_sh'][$objFleet->fleet_end_type],
    'END_COORDS'       => "[{$objFleet->fleet_end_galaxy}:{$objFleet->fleet_end_system}:{$objFleet->fleet_end_planet}]",
    'END_TIME_TEXT'    => date(FMT_DATE_TIME, $objFleet->time_arrive_to_target + SN_CLIENT_TIME_DIFF),
    'END_LEFT'         => floor($objFleet->time_arrive_to_target + 1 - SN_TIME_NOW),
    'END_URL'          => uni_render_coordinates_href($objFleet->target_coordinates_typed(), '', 3),

    'STAY_TIME' => date(FMT_DATE_TIME, $objFleet->time_mission_job_complete + SN_CLIENT_TIME_DIFF),
    'STAY_LEFT' => floor($objFleet->time_mission_job_complete + 1 - SN_TIME_NOW),
  );

  if(property_exists($objFleet, 'fleet_start_name')) {
    $result['START_NAME'] = $objFleet->fleet_start_name;
  }
  if(property_exists($objFleet, 'fleet_end_name')) {
    $result['END_NAME'] = $objFleet->fleet_end_name;
  }

  if(property_exists($objFleet, 'event_time')) {
    $result['fleet'] = array_merge($result['fleet'], array(
      'OV_LABEL'        => $objFleet->ov_label,
      'EVENT_TIME_TEXT' => property_exists($objFleet, 'event_time') ? date(FMT_DATE_TIME, $objFleet->event_time + SN_CLIENT_TIME_DIFF) : '',
      'OV_LEFT'         => floor($objFleet->event_time + 1 - SN_TIME_NOW),
      'OV_THIS_PLANET'  => $objFleet->ov_this_planet,
    ));
  }

  $ship_id = 0;
  $result['ships'] = array();
  if($spy_level >= 6) {
    foreach($objFleet->shipsIterator() as $ship_sn_id => $ship) {
      if($spy_level >= 10) {
        $single_ship_data = get_ship_data($ship_sn_id, $user_data);
        $result['ships'][$ship_sn_id] = array(
          'ID'          => $ship_sn_id,
          'NAME'        => classLocale::$lang['tech'][$ship_sn_id],
          'AMOUNT'      => $ship->count,
          'AMOUNT_TEXT' => pretty_number($ship->count),
          'CONSUMPTION' => $single_ship_data['consumption'],
          'SPEED'       => $single_ship_data['speed'],
          'CAPACITY'    => $single_ship_data['capacity'],
        );
      } else {
        $result['ships'][$ship_sn_id] = array(
          'ID'               => $ship_id++,
          'NAME'             => classLocale::$lang['tech'][UNIT_SHIPS],
          'AMOUNT'           => $ship->count,
          'AMOUNT_TEXT'      => pretty_number($ship->count),
          'CONSUMPTION'      => 0,
          'CONSUMPTION_TEXT' => '0',
          'SPEED'            => 0,
          'CAPACITY'         => 0,
        );
      }
    }
  } else {
  }

  return $result;
}

function tpl_parse_planet_que($que, $planet, $que_id) {
  $hangar_que = array();
  $que_hangar = $que['ques'][$que_id][$planet['id_owner']][$planet['id']];
  if(!empty($que_hangar)) {
    foreach($que_hangar as $que_item) {
      $hangar_que['que'][] = array('id' => $que_item['que_unit_id'], 'count' => $que_item['que_unit_amount']);
      $hangar_que[$que_item['que_unit_id']] += $que_item['que_unit_amount'];
    }
  }

  return $hangar_que;
}

function tpl_parse_planet($planet) {
  $fleet_list = FleetList::EMULATE_flt_get_fleets_to_planet($planet);

  $que = que_get($planet['id_owner'], $planet['id'], false);

  $structure_que = tpl_parse_planet_que($que, $planet, QUE_STRUCTURES); // TODO Заменить на que_tpl_parse_element($que_element);
  $structure_que_first = is_array($structure_que['que']) ? reset($structure_que['que']) : array();
  $hangar_que = tpl_parse_planet_que($que, $planet, SUBQUE_FLEET); // TODO Заменить на que_tpl_parse_element($que_element);
  $hangar_que_first = is_array($hangar_que['que']) ? reset($hangar_que['que']) : array();
  $defense_que = tpl_parse_planet_que($que, $planet, SUBQUE_DEFENSE); // TODO Заменить на que_tpl_parse_element($que_element);
  $defense_que_first = is_array($defense_que['que']) ? reset($defense_que['que']) : array();

  $result = array(
    'ID'    => $planet['id'],
    'NAME'  => $planet['name'],
    'IMAGE' => $planet['image'],

    'GALAXY'      => $planet['galaxy'],
    'SYSTEM'      => $planet['system'],
    'PLANET'      => $planet['planet'],
    'TYPE'        => $planet['planet_type'],
    'COORDINATES' => uni_render_coordinates($planet),

    'METAL_PERCENT'     => $planet['metal_mine_porcent'] * 10,
    'CRYSTAL_PERCENT'   => $planet['crystal_mine_porcent'] * 10,
    'DEUTERIUM_PERCENT' => $planet['deuterium_sintetizer_porcent'] * 10,

    'STRUCTURE' => isset($structure_que_first['id']) ? classLocale::$lang['tech'][$structure_que_first['id']] : '',

    'HANGAR'     => isset($hangar_que_first['id']) ? classLocale::$lang['tech'][$hangar_que_first['id']] : '',
    'hangar_que' => $hangar_que,

    'DEFENSE'     => isset($defense_que_first['id']) ? classLocale::$lang['tech'][$defense_que_first['id']] : '',
    'defense_que' => $defense_que,

    'FIELDS_CUR' => $planet['field_current'],
    'FIELDS_MAX' => eco_planet_fields_max($planet),
    'FILL'       => min(100, floor($planet['field_current'] / eco_planet_fields_max($planet) * 100)),

    'FLEET_OWN'     => $fleet_list['own']['count'],
    'FLEET_ENEMY'   => $fleet_list['enemy']['count'],
    'FLEET_NEUTRAL' => $fleet_list['neutral']['count'],

    'fleet_list' => $fleet_list,

    'PLANET_GOVERNOR_ID'        => $planet['PLANET_GOVERNOR_ID'],
    'PLANET_GOVERNOR_NAME'      => classLocale::$lang['tech'][$planet['PLANET_GOVERNOR_ID']],
    'PLANET_GOVERNOR_LEVEL'     => $planet['PLANET_GOVERNOR_LEVEL'],
    'PLANET_GOVERNOR_LEVEL_MAX' => get_unit_param($planet['PLANET_GOVERNOR_ID'], P_MAX_STACK),
  );

  if(!empty($que['ques'][QUE_STRUCTURES][$planet['id_owner']][$planet['id']])) {
    $result['building_que'] = array();
    $building_que = &$que['ques'][QUE_STRUCTURES][$planet['id_owner']][$planet['id']];
    foreach($building_que as $que_element) {
      $result['building_que'][] = que_tpl_parse_element($que_element);
    }
  }

  return $result;
}

/**
 * @param Fleet[] $array_of_Fleet
 *
 * @return array
 */
function flt_get_fleets_to_planet_by_array_of_Fleet($array_of_Fleet) {
  global $user;

  static $snGroupFleet;
  !$snGroupFleet ? $snGroupFleet = classSupernova::$gc->groupFleet : false;

  if(empty($array_of_Fleet)) {
    return false;
  }

  $fleet_list = array();
  foreach($array_of_Fleet as $fleet) {
    if($fleet->playerOwnerId == $user['id']) {
      if($fleet->mission_type == MT_MISSILE) {
        continue;
      }
      $fleet_ownage = 'own';
    } else {
      switch($fleet->mission_type) {
        case MT_ATTACK:
        case MT_ACS:
        case MT_DESTROY:
        case MT_MISSILE:
          $fleet_ownage = 'enemy';
        break;

        default:
          $fleet_ownage = 'neutral';
        break;

      }
    }

    $fleet_list[$fleet_ownage]['fleets'][$fleet->dbId] = $fleet;

    if($fleet->isReturning() || (!$fleet->isReturning() && $fleet->mission_type == MT_RELOCATE) || ($fleet->target_owner_id != $user['id'])) {
      foreach($fleet->shipsIterator() as $ship_id => $ship) {
        if(!empty($snGroupFleet[$ship_id])) {
          $fleet_list[$fleet_ownage]['total'][$ship_id] += $ship->count;
        }
      }
    }

    $fleet_list[$fleet_ownage]['count']++;
    $fleet_list[$fleet_ownage]['amount'] += $fleet->shipsGetTotal();
    $fleet_resources = $fleet->resourcesGetList();
    $fleet_list[$fleet_ownage]['total'][RES_METAL] += $fleet_resources[RES_METAL];
    $fleet_list[$fleet_ownage]['total'][RES_CRYSTAL] += $fleet_resources[RES_CRYSTAL];
    $fleet_list[$fleet_ownage]['total'][RES_DEUTERIUM] += $fleet_resources[RES_DEUTERIUM];
  }

  return $fleet_list;
}

function tpl_set_resource_info(template &$template, $planet_row, $fleets_to_planet = array(), $round = 0) {
  $template->assign_vars(array(
    'RESOURCE_ROUNDING' => $round,

    'ENERGY_BALANCE' => pretty_number($planet_row['energy_max'] - $planet_row['energy_used'], true, true),
    'ENERGY_MAX'     => pretty_number($planet_row['energy_max'], true, -$planet_row['energy_used']),
    'ENERGY_FILL'    => round(($planet_row["energy_used"] / ($planet_row["energy_max"] + 1)) * 100, 0),

    'PLANET_METAL'            => round($planet_row["metal"], $round),
    'PLANET_METAL_TEXT'       => pretty_number($planet_row["metal"], $round, $planet_row["metal_max"]),
    'PLANET_METAL_MAX'        => round($planet_row["metal_max"], $round),
    'PLANET_METAL_MAX_TEXT'   => pretty_number($planet_row["metal_max"], $round, -$planet_row["metal"]),
    'PLANET_METAL_FILL'       => round(($planet_row["metal"] / ($planet_row["metal_max"] + 1)) * 100, 0),
    'PLANET_METAL_PERHOUR'    => round($planet_row["metal_perhour"], 5),
    'PLANET_METAL_FLEET_TEXT' => pretty_number($fleets_to_planet[$planet_row['id']]['fleet']['METAL'], $round, true),

    'PLANET_CRYSTAL'            => round($planet_row["crystal"], $round),
    'PLANET_CRYSTAL_TEXT'       => pretty_number($planet_row["crystal"], $round, $planet_row["crystal_max"]),
    'PLANET_CRYSTAL_MAX'        => round($planet_row["crystal_max"], $round),
    'PLANET_CRYSTAL_MAX_TEXT'   => pretty_number($planet_row["crystal_max"], $round, -$planet_row["crystal"]),
    'PLANET_CRYSTAL_FILL'       => round(($planet_row["crystal"] / ($planet_row["crystal_max"] + 1)) * 100, 0),
    'PLANET_CRYSTAL_PERHOUR'    => round($planet_row["crystal_perhour"], 5),
    'PLANET_CRYSTAL_FLEET_TEXT' => pretty_number($fleets_to_planet[$planet_row['id']]['fleet']['CRYSTAL'], $round, true),

    'PLANET_DEUTERIUM'            => round($planet_row["deuterium"], $round),
    'PLANET_DEUTERIUM_TEXT'       => pretty_number($planet_row["deuterium"], $round, $planet_row["deuterium_max"]),
    'PLANET_DEUTERIUM_MAX'        => round($planet_row["deuterium_max"], $round),
    'PLANET_DEUTERIUM_MAX_TEXT'   => pretty_number($planet_row["deuterium_max"], $round, -$planet_row["deuterium"]),
    'PLANET_DEUTERIUM_FILL'       => round(($planet_row["deuterium"] / ($planet_row["deuterium_max"] + 1)) * 100, 0),
    'PLANET_DEUTERIUM_PERHOUR'    => round($planet_row["deuterium_perhour"], 5),
    'PLANET_DEUTERIUM_FLEET_TEXT' => pretty_number($fleets_to_planet[$planet_row['id']]['fleet']['DEUTERIUM'], $round, true),
  ));
}
