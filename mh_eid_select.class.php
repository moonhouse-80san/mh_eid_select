<?php
	/**
	 * @class mh_eid_select
	 * @author zero (zero@nzeo.com)
	 * @brief 최근 게시물을 출력하는 위젯
	 * @version 0.1
	 **/

	class mh_eid_select extends WidgetHandler {

		/**
		 * @brief 위젯의 실행 부분
		 *
		 * ./widgets/위젯/conf/info.xml 에 선언한 extra_vars를 args로 받는다
		 * 결과를 만든후 print가 아니라 return 해주어야 한다
		 **/
		function proc($args) {

			//기본 객체 설정
			$oModuleModel = &getModel('module');
			$oDocumentModel = &getModel('document');
			Context::set('oModuleModel',$oModuleModel);
			Context::set('oDocumentModel',$oDocumentModel);

			// 정렬 대상
			if(!in_array($args->order_target, array('list_order','update_order','regdate','rand()','voted_count','readed_count','comment_count'))) $args->order_target = 'list_order';
			//if ($args->order_target == 'random') $args->order_target = 'rand()';
			$obj->sort_index = $args->order_target;

			// 정렬 순서
			$order_type = $args->order_type;
			if(!in_array($order_type, array('asc','desc'))) $order_type = 'asc';
			$obj->order_type = $order_type=="desc"?"asc":"desc";

			// 총 목록 수
			$list_count = (int)$args->list_count;
			if(!$list_count) $list_count = 50;
			$obj->list_count = $list_count;

			// 설정값
			$args->w_width = isset($args->w_width) ? $args->w_width : '100%';
			$args->hover_bcolor = isset($args->hover_bcolor) ? $args->hover_bcolor : 'transparent';
			$args->hover_lwidth = isset($args->hover_lwidth) ? (int)$args->hover_lwidth : 1;
			$args->com_font_color = isset($args->com_font_color) ? $args->com_font_color : '#A7A7A7';
			$args->background_color = isset($args->background_color) ? $args->background_color : 'transparent';
			$args->titles_font_color = isset($args->titles_font_color) ? $args->titles_font_color : '';
			$args->top_background_color = isset($args->top_background_color) ? $args->top_background_color : 'transparent';
			$args->board_line_color = isset($args->board_line_color) ? $args->board_line_color : '#f2f2f2';
			$args->mh_n = isset($args->mh_n) ? $args->mh_n : 'one';

			$args->board_line_width = isset($args->board_line_width) ? (int)$args->board_line_width : 1;
			$args->subject_size = isset($args->subject_size) ? (int)$args->subject_size : 14;
			$args->subject_cut_size = isset($args->subject_cut_size) ? (int)$args->subject_cut_size : 0;
			$args->sm_width = isset($args->sm_width) ? (int)$args->sm_width : 100;
			$args->list_high = isset($args->list_high) ? (int)$args->list_high : 10;
			$args->level_p = isset($args->level_p) ? (int)$args->level_p : 0;
			$args->point_p = isset($args->point_p) ? (int)$args->point_p : 0;
			$args->eid_id_num = isset($args->eid_id_num) ? (int)$args->eid_id_num : 5;

			// 최근 글 표시 시간
			$widget_info->duration_new = $duration_new * 60*60;
			if(!$args->duration_new) $args->duration_new = 24;

			// 컨텐츠 시간 범위
			if(!$args->duration_article) $args->duration_article = 0;

			// 추출 시간 범위 설정
			if($args->duration_article){
				$duration_article = time()-(60*60*24*$args->duration_article);
				$duration_article = date('YmdHis',$duration_article);
				$obj->regdate = $duration_article;
			}

			// 대상 모듈
			$mid_list = explode(",",$args->mid_list);

			// module_srl 대신 mid가 넘어왔을 경우는 직접 module_srl을 구해줌
			if($mid_list) {
				$oModuleModel = &getModel('module');
				$module_srl = $oModuleModel->getModuleSrlByMid($mid_list);
			}

			// 대상 모듈 (mid_list는 기존 위젯의 호환을 위해서 처리하는 루틴을 유지. module_srl로 위젯에서 변경)
			if($args->mid_list) {
				$mid_list = explode(",",$args->mid_list);
				$oModuleModel = &getModel('module');
				if(count($mid_list)) {
					$module_srl = $oModuleModel->getModuleSrlByMid($mid_list);
				} else {
					$site_module_info = Context::get('site_module_info');
					if($site_module_info) {
						$margs->site_srl = $site_module_info->site_srl;
						$oModuleModel = &getModel('module');
						$output = $oModuleModel->getMidList($margs);
						if(count($output)) $mid_list = array_keys($output);
						$module_srl = $oModuleModel->getModuleSrlByMid($mid_list);
					}
				}
			} else $module_srl = explode(',',$args->module_srls);

			// DocumentModel::getDocumentList()를 이용하기 위한 변수 정리
			if(is_array($module_srl)) $obj->module_srl = implode(',',$module_srl);
			else $obj->module_srl = $module_srl;

			if($obj->sort_index == 'list_order') $obj->avoid_notice = -2100000000;

			/* 비밀글 설정 */
			if(($args->show_secret ?? 'N') == 'Y')
			{
				$obj->statusList = array('PUBLIC', 'SECRET');
			}
			else
			{
				$obj->statusList = array('PUBLIC');
			}

			$obj->category_srl = $args->category_srl;
			$output = executeQueryArray('widgets.mh_eid_select.getMhEidSelect.getMhEidSelect', $obj);
			$output_count = executeQueryArray('widgets.mh_eid_select.getNewestDocumentsCount', $obj);

			// document 모듈의 model 객체를 받아서 결과를 객체화 시킴
			$oDocumentModel = &getModel('document');

			// 오류가 생기면 그냥 무시
			if(!$output->toBool()) return;

			// 결과가 있으면 각 문서 객체화를 시킴
			if($order_type == "RAND") shuffle($output->data);
			if(count($output->data)) {
				foreach($output->data as $key => $attribute) {
					$document_srl = $attribute->document_srl;

					$oDocument = null;
					$oDocument = new documentItem();
					$oDocument->setAttribute($attribute);

					if($args->view_category == "on") {
						$oDocument->category = @$oDocumentModel->getCategory($attribute->category_srl);
						$oDocument->category_srl = $attribute->category_srl;
					}

					$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($attribute->module_srl);
					$oDocument->mid = $oModuleInfo->mid;

					// 확장 변수 출력
					if($args->extra_var_num) {
						$oDocument->extra_value = $oDocument->getExtraValueHTML($args->extra_var_num);
						$oDocument->extra_var_num = $args->extra_var_num;
					}

					// 모듈이 여러개일 때 메뉴 명 구함
					if((count($mid_list) > 1 || !$args->mid_list) && $args->view_menuname == "on") {
						// 탭 이름 설정
						switch($args->menuname) {
							case "browser_title" : $oDocument->menuname = $oModuleInfo->browser_title; break;
							case "title" : $oDocument->menuname = $oModuleInfo->title; break;

						}
					}

					$document_list[$key] = $oDocument;

					/* 출력되는 모듈의 번호를 구함 */
					$document_modulies[$attribute->module_srl] = $attribute->module_srl;
				}
			} else {
				$document_list = array();
			}

			//회원그룹 권한 확인
			if(Context::get('is_logged'))
			{
				$logged_info = Context::get('logged_info');

				if(!$args->group_srls)
				{
					$args->permission = true;
				}
				else
				{
					$args->permission = false;
					$group_array = explode (',', $args->group_srls); 
					foreach($logged_info->group_list as $key => $value)
					{
						if(in_array($key, $group_array))
						{
							$args->permission = true;
							break;
						}
					}
				}

			}

			// 템플릿 파일에서 사용할 변수들을 세팅
			if(count($mid_list)==1) $args->module_name = $mid_list[0];

			$args->document_list = $document_list;

			Context::set('gallery_list',$output->data);
			Context::set('widget_info', $args);

			// 템플릿의 스킨 경로를 지정 (skin, colorset에 따른 값을 설정)
			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
			Context::set('colorset', $args->colorset);

			// 템플릿 파일을 지정
			$tpl_file = 'list';

			// 템플릿 컴파일
			$oTemplate = &TemplateHandler::getInstance();
			$output = $oTemplate->compile($tpl_path, $tpl_file);
			return $output;
		}
	}

function getExtraVars()
{
  $oDocumentModel = &getModel('document');
  return $oDocumentModel->getExtraVars($this->get('module_srl'), $this->get('document_srl'));
}
function getExtraEidValue($eid)
{
  $extra_vars = $this->getExtraVars();
  foreach($extra_vars as $idx => $key)
  {
    $extra_eid[$key->eid] = $key;
  }
  return $extra_eid[$eid]->value;
}

?>