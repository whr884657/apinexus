<?php
/**
 * 文件：core/AdminNotify.php
 * 作用：管理后台顶栏待办通知汇总（审核/反馈/友链/评论/升级）
 */

class AdminNotify
{
    /**
     * 待办收件箱（供顶栏铃铛）
     * 仅汇总类型 + 条数摘要，不下发明细正文（避免面板过长）
     *
     * @return array{ok:bool,msg:string,total:int,has_pending:bool,items:array}
     */
    public static function inbox()
    {
        $items = array();
        $total = 0;

        if (!class_exists('InstallChecker') || !InstallChecker::isInstalled()) {
            return array(
                'ok'          => true,
                'msg'         => 'ok',
                'total'       => 0,
                'has_pending' => false,
                'items'       => array(),
            );
        }

        // 接口审核
        if (class_exists('ApiManager')) {
            $n = (int) ApiManager::countPendingReview();
            if ($n > 0) {
                $total += $n;
                $items[] = array(
                    'id'    => 'api_review',
                    'icon'  => 'cloud',
                    'tone'  => 'warning',
                    'title' => '接口审核',
                    'desc'  => '有 ' . $n . ' 条开发者投稿待审核',
                    'count' => $n,
                    'url'   => '/admin/api/review',
                );
            }
        }

        // 接口反馈
        if (class_exists('ApiFeedbackManager')) {
            $n = (int) ApiFeedbackManager::countPending();
            if ($n > 0) {
                $total += $n;
                $items[] = array(
                    'id'    => 'api_feedback',
                    'icon'  => 'share',
                    'tone'  => 'info',
                    'title' => '接口反馈',
                    'desc'  => '有 ' . $n . ' 条反馈待处理',
                    'count' => $n,
                    'url'   => '/admin/api/feedback',
                );
            }
        }

        // 友情链接
        if (class_exists('LinkManager')) {
            $n = (int) LinkManager::countPendingFriend();
            if ($n > 0) {
                $total += $n;
                $items[] = array(
                    'id'    => 'link_review',
                    'icon'  => 'folder',
                    'tone'  => 'warning',
                    'title' => '友情链接审核',
                    'desc'  => '有 ' . $n . ' 条友链申请待审核',
                    'count' => $n,
                    'url'   => '/admin/content/links',
                );
            }
        }

        // 评论
        if (class_exists('CommentManager')) {
            $n = (int) CommentManager::countPending();
            if ($n > 0) {
                $total += $n;
                $items[] = array(
                    'id'    => 'comment_review',
                    'icon'  => 'users',
                    'tone'  => 'info',
                    'title' => '评论审核',
                    'desc'  => '有 ' . $n . ' 条评论待审核',
                    'count' => $n,
                    'url'   => '/admin/content/comments',
                );
            }
        }

        // 系统升级：有新版本即提示
        if (class_exists('Updater')) {
            $check = Updater::checkForUpdate();
            if (!empty($check['update_available'])) {
                $remote = isset($check['remote_version']) ? (string) $check['remote_version'] : '';
                $dismissed = isset($_SESSION['vs_update_dismiss']) ? (string) $_SESSION['vs_update_dismiss'] : '';
                $total += 1;
                $verText = $remote !== '' ? (' v' . $remote) : '';
                $items[] = array(
                    'id'    => 'system_upgrade',
                    'icon'  => 'setting',
                    'tone'  => 'danger',
                    'title' => '系统升级',
                    'desc'  => $dismissed === $remote && $remote !== ''
                        ? ('有新版本' . $verText . '，请前往系统升级')
                        : ('检测到新版本' . $verText),
                    'count' => 1,
                    'url'   => '/admin/upgrade',
                );
            }
        }

        return array(
            'ok'          => true,
            'msg'         => 'ok',
            'total'       => $total,
            'has_pending' => $total > 0,
            'items'       => $items,
        );
    }
}
