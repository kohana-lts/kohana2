<?php defined('SYSPATH') OR die('No direct access allowed.'); ?>
<table class="kp-table">
<?php
foreach ($rows as $row):

$class = empty($row['class']) ? '' : ' class="'.htmlspecialchars($row['class'], ENT_QUOTES, Kohana::CHARSET).'"';
$style = empty($row['style']) ? '' : ' style="'.htmlspecialchars($row['style'], ENT_QUOTES, Kohana::CHARSET).'"';
?>
	<tr<?php echo $class; echo $style; ?>>
		<?php
		foreach ($columns as $index => $column)
		{
			$class = empty($column['class']) ? '' : ' class="'.htmlspecialchars($column['class'], ENT_QUOTES, Kohana::CHARSET).'"';
			$style = empty($column['style']) ? '' : ' style="'.htmlspecialchars($column['style'], ENT_QUOTES, Kohana::CHARSET).'"';
			$value = isset($row['data'][$index]) ? $row['data'][$index] : '';
			$value = (is_array($value) OR is_object($value)) ? '<pre>'.htmlspecialchars(print_r($value, TRUE), ENT_QUOTES | ENT_SUBSTITUTE, Kohana::CHARSET).'</pre>' : htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, Kohana::CHARSET);
			echo '<td' . $style . $class . '>' . wordwrap($value, 100, '<br />', true) . '</td>';
		}
		?>
	</tr>
<?php
endforeach;
?>
</table>
