<?php namespace Compiler\Languages\Assembly\Expressions;

use PHPParser_Node_Name;

use PHPParser_Node_Expr_Array;
use PHPParser_Node_Expr_Variable;
use PHPParser_Node_Scalar_String;
use PHPParser_Node_Expr_FuncCall;
use PHPParser_Node_Scalar_LNumber;
use PHPParser_Node_Expr_ConstFetch;
use PHPParser_Node_Expr_ArrayDimFetch;

use Compiler\Languages\Translator;

class AssignTranslator extends Translator {

	protected $assigningArray = false;

	protected $operations = array(
		'PHPParser_Node_Expr_Plus',
		'PHPParser_Node_Expr_Minus',
		'PHPParser_Node_Expr_Mul',
		'PHPParser_Node_Expr_Div',
	);

	public function translate($token)
	{
		// Handle array assignment to a variable.
		if($token->expr instanceof PHPParser_Node_Expr_Array)
		{
			$array = $this->fetchKey($token);

			$offset = 0;

			// We'll create an empty array just in case there are no items.
			$this->language->variables->createArray($array);

			// Add each item to the array.
			foreach($token->expr->items as $item)
			{
				$value = $this->language->expressionToMemory($item->value);

				// Handle setting array elements by key.
				if( ! is_null($item->key))
				{
					$key = $this->language->expressionToMemory($item->key);

					// Initialize the array element.
					$this->language->variables->get($array)->set($item->key->value, 0);
				}

				// Handle setting array elements with no specific key.
				else
				{
					// $this->language->variables->get($array)->addArrayElement($array);
					$this->language->variables->get($array)->createElement();

					$key = '_int_'.$offset;
				}

				// Now set the array element
				$this->language->addCommand('cpta', $value, $array, $key);

				$offset++;
			}
		}

		// Handle cases that aren't array declaration.
		else
		{
			$key = $this->fetchKey($token);

			// Handle function calls.
			if($token->expr instanceof PHPParser_Node_Expr_FuncCall)
			{
				$this->compiler->compile($token->expr);

				$name = $token->expr->name->parts[0];

				$this->language->addCommand('cp', $key, 'function_'.$name.'_return');
			}

			else
			{
				$value = $this->fetchValue($token);

				// Are we setting an element to an array?
				if($token->var instanceof PHPParser_Node_Expr_ArrayDimFetch)
				{
					$array = $token->var->var->name;
					$offset = $token->var->dim->value + 1;

					$this->language->variables->setArrayElement($array, $offset, 0);

					$this->language->addCommand('cpta', $value, $array, $offset);
				}

				// Otherwise, we're just setting a variable.
				else
				{
					$this->language->variables->create($key);

					// The value will be a string if the variable is being set to a label.
					// Make sure that the label actually exists. 
					if(is_string($value))
					{
						$this->language->variables->create($value);
					}

					$this->language->addCommand('cp', $key, $value);
				}
			}
		}
	}

	public function fetchKey($token)
	{
		return $token->var->name;
	}

	public function fetchValue($token)
	{
		// Just return the name of the variable, it doesn't need
		// anything special. 
		if($token->expr instanceof PHPParser_Node_Expr_Variable)
		{
			return $token->expr->name;
		}

		// Handle operations such as addition.
		$tokenExpressionClass = get_class($token->expr);

		foreach($this->operations as $operation)
		{
			if($tokenExpressionClass == $operation)
			{
				$this->compiler->compile(array($token->expr));

				return 'a'.spl_object_hash($token->expr);
			}
		}

		// Booleans.
		if($token->expr instanceof PHPParser_Node_Expr_ConstFetch)
		{
			if($token->expr->name->parts[0] == 'true')
			{
				$value = '_int_1';
			}

			else
			{
				$value = '_int_0';
			}
		}

		// Strings.
		elseif($token->expr instanceof PHPParser_Node_Scalar_String)
		{
			$value = $token->expr->value;

			throw new \Compiler\Error("Cannot assign string to variable.");
		}

		// Integers
		elseif($token->expr instanceof PHPParser_Node_Scalar_LNumber)
		{
			$value = $token->expr->value;
		}

		else
		{
			throw new \Compiler\Error("Assigning unknown type [$tokenExpressionClass] to variable.");
		}

		return $this->language->getMemoryLocationName($value);
	}
}