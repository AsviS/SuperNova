<?php
/**
 * Created by Gorlum 12.06.2017 14:47
 */

namespace Notification;


use DBAL\ActiveRecord;

class RecordNotification extends ActiveRecord {
  protected static $_tableName = 'notifications';

}