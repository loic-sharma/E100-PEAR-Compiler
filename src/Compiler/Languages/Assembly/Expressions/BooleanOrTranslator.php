<?php namespace Compiler\Languages\Assembly\Expressions;

use Compiler\Languages\Translator;

class BooleanOrTranslator extends Translator {

	public function translate($token)
	{
		$hash  = 'a'.spl_object_hash($token);
		$left  = 'a'.spl_object_hash($token->left);
		$right = 'a'.spl_object_hash($token->right);

		$this->compiler->compile($token->left);
		$this->compiler->compile($token->right);

		$this->compiler->variables->create($left);
		$this->compiler->variables->create($right);

		$this->language->addCommand('or', $hash, $left, $right);	
	}
}