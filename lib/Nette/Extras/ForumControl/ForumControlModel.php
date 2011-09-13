<?php
 /**
  * Forum Control Model
  *
  * @package   Nette\Extras\ForumControl
  * @version   $Id: ForumControlModel.php,v 1.2.0 2011/08/23 12:28:42 dostal Exp $
  * @author    Ing. Radek Dostál <radek.dostal@gmail.com>
  * @copyright Copyright (c) 2011 Radek Dostál
  * @license   GNU Lesser General Public License
  * @link      http://www.radekdostal.cz
  */

 namespace Nette\Extras\ForumControl;

 use Nette\Object;

 class ForumControlModel extends Object implements IForumControlModel
 {
   protected $forumId;

   protected $connection;

   protected $tForum;
   protected $tThreads;

   public function __construct($forumId, \NotORM $connection)
   {
     $this->forumId = (int) $forumId;
     $this->connection = $connection;

     $this->tForum = 'forum';
     $this->tThreads = 'forum_threads';
   }

    public function getCount()
    {
        $tableName = $this->tThreads;
        return (int) $this->connection->$tableName(array(
                    "id_forum" => $this->forumId
                ))->count("*");
    }

    public function getTitle()
    {
        $tableName = $this->tForum;
        return $this->connection->$tableName(array(
                    "id_forum" => $this->forumId
                ))->select('forum')->fetch();
    }

    public function getTopic($topicId)
    {
        $tableName = $this->tThreads;
        return $this->connection->$tableName(array(
                "id_forum" => $this->forumId, 
                "id_thread" => $topicId
            ))->select('*')->fetch();
    }

    public function getTopics(array $topicIds)
    {
        $tableName = $this->tThreads;

        return $this->connection->$tableName(array(
                "id_forum" => $this->forumId, 
                "id_thread" => $topicIds
            ))>select('*')
            ->order('sequence ASC');
    }

    public function getThreads()
    {
        $tableName = $this->tThreads;
        $rows = $this->connection->$tableName(array(
                    "id_forum" => $this->forumId
                ))
                ->select(
                 'id_thread, depth, name, title, topic, date_time, DATE_FORMAT(date_time, \'%e. %c. %Y, %H:%i\') AS cz_date_time')
               ->order('sequence ASC');
        return $rows;
    }

    public function insert(\Nette\ArrayHash $data, $topicId)
    {
        $tableName = $this->tThreads;
        
        $data->id_forum = $this->forumId;

        $this->connection->query('LOCK TABLES ['.$this->tThreads.'] WRITE');

        $re = $this->getTopic($topicId);
        
        if ($topicId && $re !== FALSE)
        {
            $re = $this->connection->$tableName('id_forum', $this->forumId)
                    ->select('MIN(sequence) - 1 AS new_sequence')
                    ->select($re['depth'] . ' + 1 AS new_depth')
                    ->where(new \NotORM_Literal('sequence > ' . $re['sequence']))
                    ->where(new \NotORM_Literal('depth <= ' . $re['depth']))
                    ->fetch();
            
            //\Nette\Diagnostics\Debugger::barDump($re['new_sequence']);

            if ($re['new_sequence'])
            {
                $this->connection->$tableName('id_forum', $this->forumId)
                        ->where(new \NotORM_Literal('sequence > ' . $re['new_sequence']))
                        ->update(array('sequence' => new \NotORM_Literal('sequence + 1')));
            }
            else
            {
                $re = $this->connection->$tableName("id_forum", $this->forumId)
                        ->select('MAX(sequence) AS new_sequence')
                        ->select($re['new_depth'] . ' AS new_depth')
                        ->fetch();
            }
            
        }
        else
        {
            $re = $this->connection->$tableName("id_forum", $this->forumId)
                        ->select('MAX(sequence) AS new_sequence')
                        ->select('0 AS new_depth')
                        ->fetch();
        }

        $data->sequence = $re['new_sequence'] + 1;
        $data->depth = $re['new_depth'];

        $this->connection->$tableName()->insert($data);
        $this->connection->query('UNLOCK TABLES');
    }

    public function existsTopic($topicId)
    {
        $tableName = $this->tThreads;
        return (bool) $this->connection->$tableName(array('id_forum' => $this->forumId, 'id_thread' => $topicId))->count('*');
    }

   public static function timeAgoInWords($time)
   {
     if (!$time)
       return FALSE;
     elseif (is_numeric($time))
       $time = (int) $time;
     elseif ($time instanceof DateTime)
       $time = $time->format('U');
     else
       $time = strtotime($time);

     $delta = time() - $time;
     $delta = round($delta / 60);

     if ($delta == 0)
       return 'před chvilkou';

     if ($delta == 1)
       return 'před minutou';

     if ($delta < 45)
       return 'před' . $delta . ' minutami';

     if ($delta < 90)
       return 'před hodinou';

     if ($delta < 1440)
       return 'před' . round($delta / 60).' hodinami';

     if ($delta < 2880)
       return 'včera';

     if ($delta < 43200)
       return 'před' . round($delta / 1440).' dny';

     if ($delta < 86400)
       return 'minulý měsíc';

     if ($delta < 525960)
       return 'před' . round($delta / 43200).' měsíci';

     if ($delta < 1051920)
       return 'minulý rok';

     return 'před' . round($delta / 525960).' lety';
   }
 }
?>