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
  <h3>Vehicles</h3>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Plate</th>
        <th>Category</th>
        <th>Affiliation</th>
        <th>Status</th>
        <th>Created At</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($vehicles as $vehicle)
        <tr>
          <td>{{ $vehicle->id }}</td>
          <td>{{ $vehicle->plate() }}</td>
          <td>{{ $vehicle->category?->name() }}</td>
          <td>{{ $vehicle->affiliation === 'gcm' ? 'GCM' : $vehicle->affiliation }}</td>
          <td>{{ $vehicle->operational_status }}</td>
          <td>{{ $vehicle->created_at }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
