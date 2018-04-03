<?php
/**
 * Created by gyh9711.
 * User: gyh9711 <63453409@qq.com>
 * Date: 2018/4/2
 */
namespace QL\Ext\t;
use QL\Contracts\PluginContract;
use QL\QueryList;

/**
 * QueryList扩展插件
 * @desc 2bulu.com动态数据采集插件
 * @author: 岩路 <63453409@qq.com> 2018-04-03
 */

class Bulu implements PluginContract
{
	private static $url   = 'http://www.2bulu.com//event/event_list2.htm';
	private static $baseUrl = 'http://www.2bulu.com';
    private static $range = 'li';
	private static $rules = [];
	  
    private $httpOpt = [];
    private $querylist;

	//初始
	private function __construct(QueryList $querylist)
    {
		//定义获取规则
		self::$rules = [
					'img_url' => ['img','src','',function($content){
									if (!preg_match("/^(http:\/\/|https:\/\/).*$/",$content)){  
										return self::$baseUrl.$content;
									} else {
										return $content;
									}
								}],
					'tag_name' => ['.songxian','text','-span'],
					'author' => ['.user_name','text','',function($content){return trim($content);}],
					'title' => ['.event_name','title'],
					'event_time' => ['.event_time','text'],
					'counts' => ['.event_people','text'],
					'url' => ['.event_name > a','href','',function($content){return self::$baseUrl.$content;}],
				];
        $this->querylist = $querylist->rules(self::$rules)->range(self::$range);
    }
	
	//绑定扩展
    public static function install(QueryList $querylist, ...$opts)
    {
        $name         = $opts[0] ?? 'bulu';
        $Bulu = new Bulu($querylist);
        $querylist->bind($name, function () use ($Bulu) {
            return $Bulu;
        });
    }
	

	public function setHttpOption(array $httpOpt = [])
    {
        $this->httpOpt = $httpOpt;
        return $this;
    }
	
	
	//获取指定页活动数据信息
    public function page($page = 1)
    {
        return $this->query($page)->query()->getData(function($items){
							$_date = explode('～',$items['event_time']);
							// dump($items); 
							$items['begin_date'] = $_date[0]??'';
							$items['end_date'] =$_date[1]??'';
							$items['created_at'] = $items['created_at']??$items['begin_date'];
							$items['days'] =date_diff (date_create($items['end_date']),date_create($items['begin_date']))->format('%a'); //计算天算
							$items['url_md5'] = md5($items['url']);
							$items['from'] = '两步路-活动';
							return $items;
						})->toArray();
    }
	
	/*
	* 获取指定页数或者默认所有活动数据
	*/
    public function pages($pages=null, $base = 1)
    {
		//如无指定，则获取所有数据
		$pages = $pages??$this->getPageCount();
        $results = [];
        for ($i = $base; $i <= $pages; $i++) {
            $res     = $this->page($i);
            $results = array_merge($results, $res);
        }
        return $results;
    }
	
	//返回分页总数
    public function getPageCount()
    {
        $count = $this->getPageInfo()['totalPage']??0;
        return $count;
    }
	
	/*
	* 返回活动分页信息
	*/
    public function getPageInfo()
    {
		// return $this->query()->find('.pagination')->attr('page');
		return ext_json_decode($this->query()->find('.pagination')->attr('page'),true);
    }
	
	
	/*
	* 通过接口请求获取数据
	* @param int $page 获取第几页数据
	* @param enum $type 获取线路类型  0:所有， 1:AA  2:商业， 3:
	* @return QueryList  返回Querylist 对象
	*/
    private function query($page = 1,$type = 0)
    {
		// echo 'start query';
        $this->querylist->post(self::$url, ['pageNumber'=>$page,
						'type'=>$type,
						'tabFlag'=>'soon',
						'startAddress'=>'深圳',
						'cost'=>'',
						'addressId'=>'0_0',
						'message'=>''
					],
					$this->httpOpt
				);
		// echo 'start query';
        return $this->querylist;
    }
}