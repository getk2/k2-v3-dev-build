<?php
/**
 * @version		3.0.0
 * @package		K2
 * @author		JoomlaWorks http://www.joomlaworks.net
 * @copyright	Copyright (c) 2006 - 2014 JoomlaWorks Ltd. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 */

// no direct access
defined('_JEXEC') or die ; ?>

<div id="k2ModuleBox<?php echo $module->id; ?>" class="k2ItemsBlock<?php if($params->get('moduleclass_sfx')) echo ' '.$params->get('moduleclass_sfx'); ?>">

	<?php if($params->get('itemPreText')): ?>
	<p class="modulePretext"><?php echo $params->get('itemPreText'); ?></p>
	<?php endif; ?>

	<?php if(count($items)): ?>
  <ul>
    <?php foreach ($items as $key=>$item):	?>
    <li class="<?php echo ($key%2) ? "odd" : "even"; if(count($items)==$key+1) echo ' lastItem'; ?>">

      <!-- K2 Plugins: K2BeforeDisplay -->
      <?php echo $item->events->K2BeforeDisplay; ?>

      <?php if($params->get('itemAuthorAvatar')): ?>
      <a class="k2Avatar moduleItemAuthorAvatar" rel="author" href="<?php echo $item->author->link; ?>">
				<img src="<?php echo $item->author->image->src; ?>" alt="<?php echo htmlspecialchars($item->author->name); ?>" style="width:<?php echo $params->get('itemAuthorAvatarWidth'); ?>px; height:auto;" />
			</a>
      <?php endif; ?>

      <?php if($params->get('itemTitle')): ?>
      <a class="moduleItemTitle" href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
      <?php endif; ?>

      <?php if($params->get('itemAuthor')): ?>
      <div class="moduleItemAuthor">
	      <?php echo K2HelperUtilities::writtenBy($item->author->gender); ?>
	
				<?php if(isset($item->author->link)): ?>
				<a rel="author" title="<?php echo htmlspecialchars($item->author->name); ?>" href="<?php echo $item->author->link; ?>"><?php echo $item->author->name; ?></a>
				<?php else: ?>
				<?php echo $item->author->name; ?>
				<?php endif; ?>
				
				<?php if($params->get('userDescription')): ?>
				<?php echo $item->author->description; ?>
				<?php endif; ?>
				
			</div>
			<?php endif; ?>

      <!-- Plugins: AfterDisplayTitle -->
      <?php echo $item->events->AfterDisplayTitle; ?>

      <!-- K2 Plugins: K2AfterDisplayTitle -->
      <?php echo $item->events->K2AfterDisplayTitle; ?>

      <!-- Plugins: BeforeDisplayContent -->
      <?php echo $item->events->BeforeDisplayContent; ?>

      <!-- K2 Plugins: K2BeforeDisplayContent -->
      <?php echo $item->events->K2BeforeDisplayContent; ?>

      <?php if($params->get('itemImage') || $params->get('itemIntroText')): ?>
      <div class="moduleItemIntrotext">
	      <?php if($params->get('itemImage') && $item->image): ?>
	      <a class="moduleItemImage" href="<?php echo $item->link; ?>" title="<?php echo JText::_('K2_CONTINUE_READING'); ?> &quot;<?php echo htmlspecialchars($item->title); ?>&quot;">
	      	<img src="<?php echo $item->image->src; ?>" alt="<?php echo htmlspecialchars($item->image->alt); ?>"/>
	      </a>
	      <?php endif; ?>

      	<?php if($params->get('itemIntroText')): ?>
      	<?php echo $item->introtext; ?>
      	<?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if($params->get('itemExtraFields') && count($item->extraFieldsGroups)): ?>
      <div class="moduleItemExtraFields">
	      <b><?php echo JText::_('K2_ADDITIONAL_INFO'); ?></b>
	      
		  <?php foreach ($item->extra_fields as $extraField): ?>
		  <ul>
			<?php if($extraField->output): ?>
			<li class="<?php echo ($key%2) ? "odd" : "even"; ?> type<?php echo ucfirst($extraField->type); ?> group<?php echo $extraField->group; ?>">
				<span class="moduleItemExtraFieldsLabel"><?php echo $extraField->name; ?>:</span>
				<span class="moduleItemExtraFieldsValue"><?php echo $extraField->output; ?></span>
				<div class="clr"></div>
			</li>
			<?php endif; ?>
		</ul>
		<?php endforeach; ?>
		<div class="clr"></div>
      </div>
      <?php endif; ?>

      <div class="clr"></div>

  <?php if(!empty($item->media) && $params->get('itemVideo')): ?>
  <!-- Item media -->
  <a name="itemMediaAnchor" id="itemMediaAnchor"></a>
  
  <div class="itemMediaBlock">
  	<h3><?php echo JText::_('K2_MEDIA'); ?></h3>
  	<?php foreach ($item->media as $entry) : ?>
	<div class="itemMedia">
  	
	
	<span class="itemMediaOutput"><?php echo $entry->output; ?></span>

	<?php if(!empty($entry->caption)): ?>
	<span class="itemMediaCaption"><?php echo $entry->caption; ?></span>
	<?php endif; ?>

	<?php if(!empty($entry->credits)): ?>
	<span class="itemMediaCredits"><?php echo $entry->credits; ?></span>
	<?php endif; ?>

	  <div class="clr"></div>
  </div> 
	<?php endforeach; ?>
  </div>
	
  <?php endif; ?>

      <div class="clr"></div>

      <!-- Plugins: AfterDisplayContent -->
      <?php echo $item->events->AfterDisplayContent; ?>

      <!-- K2 Plugins: K2AfterDisplayContent -->
      <?php echo $item->events->K2AfterDisplayContent; ?>

      <?php if($params->get('itemDateCreated')): ?>
      <span class="moduleItemDateCreated"><?php echo JText::_('K2_WRITTEN_ON') ; ?> <?php echo JHtml::_('date', $item->created, JText::_('K2_DATE_FORMAT_LC2')); ?></span>
      <?php endif; ?>

      <?php if($params->get('itemCategory')): ?>
      <?php echo JText::_('K2_IN') ; ?> <a class="moduleItemCategory" href="<?php echo $item->category->link; ?>"><?php echo $item->category->title; ?></a>
      <?php endif; ?>

      <?php if($params->get('itemTags') && count($item->tags)>0): ?>
      <div class="moduleItemTags">
      	<b><?php echo JText::_('K2_TAGS'); ?>:</b>
        <?php foreach ($item->tags as $tag): ?>
        <a href="<?php echo $tag->link; ?>"><?php echo $tag->name; ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if($params->get('itemAttachments') && count($item->attachments)): ?>
			<div class="moduleAttachments">
				<?php foreach ($item->attachments as $attachment): ?>
				<a title="<?php echo htmlspecialchars($attachment->title); ?>" href="<?php echo $attachment->link; ?>"><?php echo $attachment->title; ?></a>
				<?php endforeach; ?>
			</div>
      <?php endif; ?>

			<?php if($params->get('itemCommentsCounter') && $componentParams->get('comments')): ?>		
				<?php if(!empty($item->events->K2CommentsCounter)): ?>
					<!-- K2 Plugins: K2CommentsCounter -->
					<?php echo $item->events->K2CommentsCounter; ?>
				<?php else: ?>
					<?php if($item->numOfComments>0): ?>
					<a class="moduleItemComments" href="<?php echo $item->link.'#itemCommentsAnchor'; ?>">
						<?php echo $item->numOfComments; ?> <?php if($item->numOfComments>1) echo JText::_('K2_COMMENTS'); else echo JText::_('K2_COMMENT'); ?>
					</a>
					<?php else: ?>
					<a class="moduleItemComments" href="<?php echo $item->link.'#itemCommentsAnchor'; ?>">
						<?php echo JText::_('K2_BE_THE_FIRST_TO_COMMENT'); ?>
					</a>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>

			<?php if($params->get('itemHits')): ?>
			<span class="moduleItemHits">
				<?php echo JText::_('K2_READ'); ?> <?php echo $item->hits; ?> <?php echo JText::_('K2_TIMES'); ?>
			</span>
			<?php endif; ?>

			<?php if($params->get('itemReadMore') && $item->fulltext): ?>
			<a class="moduleItemReadMore" href="<?php echo $item->link; ?>">
				<?php echo JText::_('K2_READ_MORE'); ?>
			</a>
			<?php endif; ?>

      <!-- K2 Plugins: K2AfterDisplay -->
      <?php echo $item->events->K2AfterDisplay; ?>

      <div class="clr"></div>
    </li>
    <?php endforeach; ?>
    <li class="clearList"></li>
  </ul>
  <?php endif; ?>

	<?php if($params->get('itemCustomLink')): ?>
	<a class="moduleCustomLink" href="<?php echo $params->get('itemCustomLinkURL'); ?>" title="<?php echo htmlspecialchars($params->get('itemCustomLinkTitle')); ?>"><?php echo $params->get('itemCustomLinkTitle'); ?></a>
	<?php endif; ?>

	<?php if($params->get('feed')): ?>
	<div class="k2FeedIcon">
		<a href="<?php echo JRoute::_('index.php?option=com_k2&view=itemlist&task=module&id='.$module->id.'&format=feed&Itemid='); ?>" title="<?php echo JText::_('K2_SUBSCRIBE_TO_THIS_RSS_FEED'); ?>">
			<span><?php echo JText::_('K2_SUBSCRIBE_TO_THIS_RSS_FEED'); ?></span>
		</a>
		<div class="clr"></div>
	</div>
	<?php endif; ?>

</div>
