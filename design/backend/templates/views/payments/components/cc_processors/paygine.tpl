
{assign var="return_url" value="`$config.http_location`/payments/paygine.php"}
<p style="display: none;">{$lang.text_paygine_notice|replace:"[return_url]":$return_url}</p>
<hr />

<div class="form-field">
	<label for="account_id">Номер сектора:</label>
	<input type="text" name="payment_data[processor_params][project_id]" id="project_id" value="{$processor_params.project_id}" class="input-text"  size="60" />
</div>

<div class="form-field">
	<label for="account_id">Пароль:</label>
	<input type="text" name="payment_data[processor_params][sign]" id="sign" value="{$processor_params.sign}" class="input-text"  size="60" />
</div>

<div class="form-field">
	<label for="test">Тестовый режим:</label>
	<select name="payment_data[processor_params][test]" id="test">
		<option value="1" {if $processor_params.test == "1"}selected="selected"{/if}>Yes</option>
		<option value="0" {if $processor_params.test == "0"}selected="selected"{/if}>No</option>
	</select>
</div>

<div class="form-field">
	<label for="tax">Ставка НДС:</label>
	<select name="payment_data[processor_params][tax]" id="tax">
		<option value="1" {if $processor_params.tax == "1"}selected="selected"{/if}>ставка НДС 18%</option>
		<option value="2" {if $processor_params.tax == "2"}selected="selected"{/if}>ставка НДС 10%</option>
		<option value="3" {if $processor_params.tax == "3"}selected="selected"{/if}>ставка НДС расч. 18/118</option>
		<option value="4" {if $processor_params.tax == "4"}selected="selected"{/if}>ставка НДС расч. 10/110</option>
		<option value="5" {if $processor_params.tax == "5"}selected="selected"{/if}>ставка НДС 0%</option>
		<option value="6" {if $processor_params.tax == "6"}selected="selected"{/if}{if $processor_params.tax == "0"}selected="selected"{/if}{if $processor_params.tax == ""}selected="selected"{/if}>НДС не облагается</option>
	</select>
</div>
