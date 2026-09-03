<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: sans-serif; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
    th { background: #f0f0f0; }
  </style>
</head>
<body>
  <h3>Asset capacity categories</h3>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Applies To</th>
        <th>Capacity (CBM)</th>
        <th>Capacity (TON)</th>
        <th>Created At</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($categories as $category)
        <tr>
          <td>{{ $category->id }}</td>
          <td>{{ $category->name }}</td>
          <td>{{ $category->applies_to }}</td>
          <td>{{ $category->capacity_cbm }}</td>
          <td>{{ $category->capacity_ton }}</td>
          <td>{{ $category->created_at }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
