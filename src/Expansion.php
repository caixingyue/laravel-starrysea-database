<?php

namespace Starrysea\Database;

use Starrysea\Arrays\Arrays;
use Starrysea\Usually\Convert;

trait Expansion
{
    /**
     * 格式化时间戳
     * @param $query
     * @param string $field 时间戳
     * @param string $date 指定返回的时间格式[默认返回年月日时分秒]
     * @return $this
     */
    public function scopeUnixtime($query, string $field, string $date = '%Y-%m-%d %H:%i:%s')
    {
        $timed = $field;
        $timea = $field;
        if (stripos($field,' as ')){
            $time  = str_replace(' AS ',' as ', $field);
            $time  = str_replace(' As ',' as ', $time);
            $time  = str_replace(' aS ',' as ', $time);
            $time  = explode(' as ', $time);
            $timea = trim($time[1]);
            $timed = trim($time[0]);
        }
        return $query->selectRaw(sprintf('FROM_UNIXTIME(%s, \'%s\') as %s', $timed, $date, $timea));
    }

    /**
     * 查询条件[只有当数据存在时才会加入该条件]
     * @param $query
     * @param string $filed 字段
     * @param string|int $condition 内容或条件
     * @param string|int $value 内容
     * @return $this
     */
    public function scopeIsWhere($query, string $filed, $condition, $value = '')
    {
        $where = ['=', '<>', '>', '<', '>=', '<=', 'between', 'like', '!='];
        if (in_array(strtolower($condition), $where) && ((string) $value || (string) $value === '0'))
            return $query->where($filed, $condition, $value);
        else if (!in_array(strtolower($condition), $where) && ((string) $condition || (string) $condition === '0'))
            return $query->where($filed, $condition);
        else
            return $query;
    }

    /**
     * 查询或条件[只有当数据存在时才会加入该条件]
     * @param $query
     * @param string $filed 字段
     * @param string|int $condition 内容或条件
     * @param string|int $value 内容
     * @return $this
     */
    public function scopeIsorWhere($query, string $filed, $condition, $value = '')
    {
        $where = ['=', '<>', '>', '<', '>=', '<=', 'between', 'like', '!='];
        if (in_array(strtolower($condition), $where) && ((string) $value || (string) $value === '0'))
            return $query->orwhere($filed, $condition, $value);
        else if (!in_array(strtolower($condition), $where) && ((string) $condition || (string) $condition === '0'))
            return $query->orwhere($filed, $condition);
        else
            return $query;
    }

    /**
     * 精确及分词模糊查询[只有当数据存在时才会加入该条件]
     * @param $query
     * @param string $data 内容
     * @param array|string $accurate 精确查询字段
     * @param array|string $participle 分词模糊查询字段
     * @return $this
     */
    public function scopeIsWhereBranchsieve($query, string $data = '', $accurate = [], $participle = [])
    {
        $accurate   = Arrays::toArray($accurate);
        $participle = Arrays::toArray($participle);
        $accurate   = array_filter($accurate);
        $participle = array_filter($participle);
        return $query->where(function ($query) use ($data, $accurate, $participle){
            // 精确条件
            foreach ($accurate as $key=>$filed){
                if ($key === 0){
                    $query->isWhere($filed, $data);
                }else{
                    $query->isorWhere($filed, $data);
                }
            }

            // 模糊条件
            $query->worddivision($data, function ($query, $words) use ($participle){
                foreach ($words as $key=>$value){
                    if ($key === 0){
                        $query->orwhere(function ($query) use ($value, $participle){
                            foreach ($participle as $arrfield){
                                $query->orwhere($arrfield, 'like', '%' . $value . '%');
                            }
                        });
                    }else{
                        $query->where(function ($query) use ($value, $participle){
                            foreach ($participle as $arrfield){
                                $query->orwhere($arrfield, 'like', '%' . $value . '%');
                            }
                        });
                    }
                }
            });
        });
    }

    /**
     * 分词
     * @param $query
     * @param string $data 内容
     * @param null $success 分词有内容回调
     * @param null $error 分词无内容回调
     * @return $this
     */
    public function scopeWorddivision($query, string $data = '', $success = null, $error = null)
    {
        $words = Convert::Pscws4()->send_text($data)->set_ignore(true)->get_result('word'); // 开始分词
        if (!empty($words) && is_callable($success))
            call_user_func($success, $query, $words, $data);
        elseif (is_callable($error))
            call_user_func($error, $query, $words, $data);
        return $query;
    }

    /**
     * 权限范围限制
     * 特别说明：account_model = true 的情况下,不可对同为全局管理员进行操作,旗下管理员不受此约束
     * @param $query
     * @param string $filed 条件字段
     * @return $this
     */
    public function scopeAuthority($query, string $filed = 'user_id')
    {
        $user = auth()->user();
        if ($user->sid == 0){
            return $query;
        }elseif ($user->authority == 1){
            return $query->where($filed, $user->id);
        }elseif ($this->account_model === true){
            return $query->where(function ($query) use ($filed, $user){
                $query->where('authority', '<>', 0);
                $query->orwhere($filed, $user->id);
            });
        }else{
            return $query;
        }
    }

    /**
     * 移除系统管理员及登录人的数据
     * @param $query
     * @return $this
     */
    public function scopeResysmanager($query)
    {
        return $query->where('sid', '<>', 0)->where('id', '<>', auth()->user()->id);
    }
}