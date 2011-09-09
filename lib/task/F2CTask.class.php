<?php
class F2CTask extends sfBaseTask
{
  protected function configure()
  {
    set_time_limit(120);
    mb_language("Japanese");
    mb_internal_encoding("utf-8");
    $this->namespace        = 'zuniv.us';
    $this->name             = 'Friend2Community';
    $this->aliases          = array('zu-f2c');
    $this->briefDescription = '';
  }
  protected function execute($arguments = array(), $options = array())
  {
    $databaseManager = new sfDatabaseManager($this->configuration);
    self::createCommunity();
    self::friend2community();
  }
  public static function createCommunity(){
    $member_list = Doctrine_Query::create()->from("Member m")->where("is_active = ?",1)->execute();
    foreach($member_list as $member){
      print_r($member->name);

      //community exists?
      $is_c = Doctrine::getTable("Community")->find(10000000 + (int)$member->id);
      if($is_c){
        //update
        echo "MEMBER_ID={$member->id} COMMUNITY EXISTS.\n";
        $is_c->name = $member->name. "さんのフレンドコミュニティ";
        $is_c->save();
        echo "UPDATE COMMUNITY NAME TO {$is_c->name}.\n";
      }else{
        //create
        $c = new Community();
        $c->id = 10000000 + (int)$member->id;
        $c->name = $member->name . "さんのフレンドコミュニティ";
        $c->community_category_id = 1;
        $c->save();

        $cm = new CommunityMember();
        $cm->community_id = $c->id;
        $cm->member_id = $member->id;
        $cm->save();

        $cmp = new CommunityMemberPosition();
        $cmp->community_id = $c->id;
        $cmp->member_id = $member->id;
        $cmp->community_member_id = $cm->id;
        $cmp->name = "admin";
        $cmp->save();
        echo "COMMUNITY ADMIN ADDED.\n";

        $cc = new CommunityConfig();
        $cc->community_id = $c->id;
        $cc->name = 'public_flag';
        $cc->value = 'public';
        $cc->save();

        $cc = new CommunityConfig();
        $cc->community_id = $c->id;
        $cc->name = 'public_authority';
        $cc->value = 'public';
        $cc->save();

        $cc = new CommunityConfig();
        $cc->community_id = $c->id;
        $cc->name = 'register_policy';
        $cc->value = 'close';
        $cc->save();

        $cc = new CommunityConfig();
        $cc->community_id = $c->id;
        $cc->name = 'description';
        $cc->value = 'auto generated community';
        $cc->save();

        $cc = new CommunityConfig();
        $cc->community_id = $c->id;
        $cc->name = 'is_send_pc_joinCommunity_mail';
        $cc->value = 1;
        $cc->save();
        echo "<NEW COMMUNITY CREATED> {$c->name}.\n";
      }
    }
  }
  public static function friend2community(){
    $member_list = Doctrine_Query::create()->from("Member m")->where("is_active = ?",1)->fetchArray();
    foreach($member_list as $member){
      $community = Doctrine::getTable("Community")->find(10000000 + (int)$member["id"]);
      $mr_list = Doctrine_Query::create()->select("mr.member_id_to")->from("MemberRelationship mr")->where("mr.member_id_from = ?",$member['id'])->fetchArray();
      $notin = array($member['id']);
      foreach($mr_list as $mr){
        $notin[] = $mr["member_id_to"];
      }
      //入っているべきでない人を除外する
      Doctrine_Query::create()->delete()->from("CommunityMember cm")->where("cm.community_id = ?",$community["id"])->andWhereNotIn("cm.member_id",$notin)->execute();
      Doctrine_Query::create()->delete()->from("CommunityMemberPosition cmp")->where("cmp.community_id = ?",$community["id"])->andWhereNotIn("cmp.member_id",$notin)->execute();

      //入っているべき人を入れる
      foreach($mr_list as $mr){
       $_cm = Doctrine_Query::create()->from("CommunityMember cm")->where("cm.member_id = ?",$mr['member_id_to'])->addWhere("cm.community_id = ?",$community["id"])->fetchOne(array(),Doctrine::HYDRATE_ARRAY);
        if($_cm){
          //skip
          echo "  MEMBER_ID={$mr['member_id_to']} IS A MEMBER OF COMMUNITY_ID={$community['id']} SKIP.\n";
        }else{
          $cm = new CommunityMember();
          $cm->community_id = $community["id"];
          $cm->member_id = $mr["member_id_to"];
          $cm->save();
          echo "  MEMBER_ID={$mr['member_id_to']} ADDED TO COMMUNITY_ID={$community['id']}.\n";
        }
      }
    }
  }
}
