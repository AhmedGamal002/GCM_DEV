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
  <h3>Assets</h3>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Type</th>
        <th>Capacity Category</th>
        <th>Affiliation</th>
        <th>Status</th>
        <th>Created At</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($assets as $asset)
        <tr>
          <td>{{ $asset->id }}</td>
          <td>{{ $asset->name }}</td>
          <td>{{ $asset->asset_type }}</td>
          <td>{{ $asset->capacityCategory?->name }}</td>
          <td>{{ $asset->affiliation === 'gcm' ? 'GCM' : $asset->affiliation }}</td>
          <td>{{ $asset->operational_status }}</td>
          <td>{{ $asset->created_at }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
