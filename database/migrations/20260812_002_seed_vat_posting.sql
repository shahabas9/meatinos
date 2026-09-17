UPDATE journal_lines jl
JOIN journal_entries je ON je.id=jl.journal_entry_id
JOIN chart_of_accounts coa ON coa.id=jl.account_id
JOIN invoices i ON i.id=je.reference_id
SET jl.credit=i.subtotal,
    jl.description='Poultry sales revenue'
WHERE je.reference_type='invoice'
  AND je.description='Sales invoice posted'
  AND coa.account_code='4000'
  AND jl.description='Sales revenue including GST demo posting';

INSERT INTO journal_lines (journal_entry_id,account_id,debit,credit,description)
SELECT je.id,vat.id,0,i.tax_amount,'Output GST payable'
FROM journal_entries je
JOIN invoices i ON i.id=je.reference_id
JOIN chart_of_accounts vat ON vat.account_code='2100'
WHERE je.reference_type='invoice'
  AND je.description='Sales invoice posted'
  AND i.tax_amount>0
  AND NOT EXISTS (
      SELECT 1 FROM journal_lines existing
      WHERE existing.journal_entry_id=je.id AND existing.account_id=vat.id
  );
