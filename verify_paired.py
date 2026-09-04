import pandas as pd

df = pd.read_excel('dataset/data_paired.xlsx', sheet_name='Data')
print('DataFrame shape:', df.shape)
print('\nColumns:')
for i, col in enumerate(df.columns, 1):
    print(f'  {i}. {col}')

print('\nSample data (row 1):')
print(f'  Prompt Asli: {df["Prompt Asli"].iloc[0][:60]}...')
print(f'  Prompt Parafrase: {df["Prompt Parafrase"].iloc[0][:60]}...')
print(f'  Sumber: {df["Sumber"].iloc[0]}')

print('\n✓ data_paired.xlsx ready for use!')
print(f'✓ Total pairs: {len(df)}')
