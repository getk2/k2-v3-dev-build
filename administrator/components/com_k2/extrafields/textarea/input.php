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
<?php 
require_once JPATH_ADMINISTRATOR.'/components/com_k2/classes/editor.php';
$config = JFactory::getConfig();
$editor = K2Editor::getInstance($config->get('editor'));
?>

<?php if($field->get('editor')) : ?>
	<?php echo $editor->display($field->get('prefix').'[value]', $field->get('value'), '100%', '300', (int)$field->get('rows', '10'), (int)$field->get('columns', '40')); ?>
<?php else : ?>
	<textarea rows="<?php echo (int)$field->get('rows', '10'); ?>" cols="<?php echo (int)$field->get('columns', '40'); ?>" name="<?php echo $field->get('prefix'); ?>[value]"><?php echo $field->get('value'); ?></textarea>
<?php endif; ?>

<?php if($this->required): ?>
<script type="text/javascript">
	jQuery(document).bind('K2ExtraFieldsValidate', function(event, K2ExtraFields) {
		
		<?php if($field->get('editor')) : ?>

		K2Editor.save('<?php echo $field->get('prefix'); ?>[value]');
		if(K2Editor.getContent('<?php echo $field->get('prefix'); ?>[value]') == '') {
			K2ExtraFields.addValidationError(<?php echo $this->id; ?>);
		}
		
		<?php else : ?>
		
		var element = jQuery('textarea[name="<?php echo $field->get('prefix'); ?>[value]"]');
		if(element.val() == '') {
			K2ExtraFields.addValidationError(<?php echo $this->id; ?>);
		}
		
		<?php endif; ?>
	});
</script>
<?php else : ?>
<script type="text/javascript">
jQuery(document).bind('K2ExtraFieldsValidate', function(event, K2ExtraFields) {
	
	<?php if($field->get('editor')) : ?>

	K2Editor.save('<?php echo $field->get('prefix'); ?>[value]');
		
	<?php endif; ?>
});
</script>
<?php endif; ?>
