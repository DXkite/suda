<?php  class Class34294da453628d22d8e73735c0371213 extends suda\template\compiler\suda\Template { protected $name="d85c842beb49d162241f3abc53db5e32";protected $module=""; protected function _render_template() {  ?># <?php echo htmlspecialchars(__($this->get("functionName"))); ?>

<?php echo $this->get("functionDoc",'该函数暂时无注释文档'); ?>

> *文件信息* <?php echo htmlspecialchars(__($this->get("fileName",'未知文件'))); ?>: <?php echo htmlspecialchars(__($this->get("lineStart",'未知'))); ?>~<?php echo htmlspecialchars(__($this->get("lineEnd",'未知'))); ?>

<?php echo $this->get("document",'该函数暂时无说明'); ?>

## 参数

<?php if(count($this->get("params",[]))): ?>
| 参数名 | 类型 | 默认值 | 说明 |
|--------|-----|-------|-------|
<?php foreach($this->get("params")as $name => $param): ?>| <?php echo htmlspecialchars($name); ?> |  <?php echo htmlspecialchars($param['type'] ??['无']); ?> | <?php echo htmlspecialchars($param['default']??'无'); ?> | <?php echo htmlspecialchars($param['description']??'无'); ?> |
<?php endforeach; ?>
<?php else: ?>
无参数
<?php endif; ?>

## 返回值
<?php if(count($this->get("return",[]))): ?>
类型：<?php echo htmlspecialchars(__($this->get("return")['type'])); ?>

<?php echo htmlspecialchars($this->get("return")['description']); ?>

<?php else: ?>
返回值类型不定
<?php endif; ?>

## 例子

<?php echo $this->get("example"); ?><?php }}