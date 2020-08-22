{if $list_total > $limit_per_page}
	<div class="row">
		<div class="col-lg-4 pull-right clearfix">
			<ul class="pagination pull-right">
				<li {if $page <= 1}class="disabled"{/if}>
					<a href="javascript:void(0);" class="pagination-link" data-page="1">
						<i class="icon-double-angle-left"></i>
					</a>
				</li>
				<li {if $page <= 1}class="disabled"{/if}>
					<a href="javascript:void(0);" class="pagination-link" data-page="{$page|intval - 1}">
						<i class="icon-angle-left"></i>
					</a>
				</li>
				{assign p 0}
				{while $p++ < $total_pages}
					{if $p < $page-2}
						<li class="disabled">
							<a href="javascript:void(0);">&hellip;</a>
						</li>
						{assign p $page-3}
					{else if $p > $page+2}
						<li class="disabled">
							<a href="javascript:void(0);">&hellip;</a>
						</li>
						{assign p $total_pages}
					{else}
						<li {if $p == $page}class="active"{/if}>
							<a href="javascript:void(0);" class="pagination-link" data-page="{$p|intval}">{$p|intval}</a>
						</li>
					{/if}
				{/while}
				<li {if $page > $total_pages}class="disabled"{/if}>
					<a href="javascript:void(0);" class="pagination-link" data-page="{$page|intval + 1}">
						<i class="icon-angle-right"></i>
					</a>
				</li>
				<li {if $page > $total_pages}class="disabled"{/if}>
					<a href="javascript:void(0);" class="pagination-link" data-page="{$total_pages|intval}">
						<i class="icon-double-angle-right"></i>
					</a>
				</li>
			</ul>
			<script type="text/javascript">
				$('.pagination-link').on('click',function(e){
					e.preventDefault();
					location='{$url}&page='+$(this).data("page");
				});
			</script>
		</div>
	</div>
{/if}